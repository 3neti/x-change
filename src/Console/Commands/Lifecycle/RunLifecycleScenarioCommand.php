<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Lifecycle;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use LBHurtado\ModelChannel\Contracts\HasMobileChannel;
use LBHurtado\XChange\Actions\PayCode\EstimatePayCodeCost;
use LBHurtado\XChange\Actions\PayCode\GeneratePayCode;
use LBHurtado\XChange\Actions\Redemption\SubmitPayCodeClaim;
use LBHurtado\XChange\Contracts\VoucherAccessContract;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use RuntimeException;

class RunLifecycleScenarioCommand extends Command
{
    protected $signature = 'xchange:lifecycle:run
        {scenario : Scenario key from x-change.lifecycle.scenarios}
        {--provider=netbank : Provider label}
        {--issuer= : Issuer user id}
        {--wallet= : Wallet owner/user id}
        {--amount= : Override scenario amount}
        {--prepare : Run xchange:lifecycle:prepare first}
        {--fresh : Prepare with migrate:fresh and lifecycle seeders}
        {--no-claim : Generate but do not claim}
        {--check-only= : Existing voucher code to check only}
        {--timeout= : Poll timeout in seconds}
        {--poll= : Poll interval in seconds}
        {--json : Output JSON}';

    protected $description = 'Run a named lifecycle scenario.';

    public function handle(
        EstimatePayCodeCost $estimatePayCodeCost,
        GeneratePayCode $generatePayCode,
        SubmitPayCodeClaim $submitPayCodeClaim,
        VoucherAccessContract $vouchers,
    ): int {
        if ($this->option('prepare') || $this->option('fresh')) {
            Artisan::call('xchange:lifecycle:prepare', array_filter([
                '--fresh' => (bool) $this->option('fresh'),
                '--seed' => true,
            ]));
            $this->line(Artisan::output());
        }

        $existingCode = $this->option('check-only');
        if (is_string($existingCode) && trim($existingCode) !== '') {
            return $this->runCheckOnly(trim($existingCode));
        }

        $scenarioKey = (string) $this->argument('scenario');
        $scenario = $this->resolveScenario($scenarioKey);

        if ($scenario === null) {
            $this->error("Unknown scenario: {$scenarioKey}");

            return self::FAILURE;
        }

        $this->assertLifecycleUserModelSupportsMobile();

        $scenario = array_replace_recursive(
            (array) config('x-change.lifecycle.defaults', []),
            $scenario
        );

        $issuerId = (int) ($this->option('issuer') ?: $scenario['issuer_id'] ?? 1);
        $walletId = (int) ($this->option('wallet') ?: $scenario['wallet_id'] ?? 1);
        $amount = (float) ($this->option('amount') ?: $scenario['amount'] ?? 25);
        $timeout = (int) ($this->option('timeout') ?: $scenario['timeout'] ?? 180);
        $poll = (int) ($this->option('poll') ?: $scenario['poll'] ?? 10);

        $issuer = $this->resolveIssuerModel($issuerId);
        $claimMobile = $this->resolveScenarioMobile($scenario, $issuer);

        if (! $this->option('json')) {
            $this->info("Running scenario: {$scenarioKey}");
            $this->line('Estimating cost...');
        }

        $lifecycleInput = $this->buildLifecycleInput($scenario, $issuerId, $walletId, $amount);
        $estimate = $estimatePayCodeCost->handle($lifecycleInput)->toArray();

        if (! $this->option('json')) {
            $this->line('Generating voucher...');
        }

        $generated = $generatePayCode->handle($lifecycleInput);
        $code = $generated->code;

        if ($this->option('no-claim')) {
            return $this->renderResult([
                'scenario' => $scenarioKey,
                'label' => $scenario['label'] ?? $scenarioKey,
                'issuer' => $this->formatUserSummary($issuer),
                'claim_mobile' => $claimMobile,
                'estimate' => $estimate,
                'generated' => $generated->toArray(),
            ]);
        }

        if (! $this->option('json')) {
            $this->line("Submitting claim for voucher {$code}...");
        }

        $voucher = $vouchers->findByCodeOrFail($code);
        $claimPayload = $this->buildClaimPayload($scenario, $claimMobile);
        $claim = $submitPayCodeClaim->handle($voucher, $claimPayload);

        if (! $this->option('json')) {
            $this->line('Polling disbursement status...');
        }

        $finalCheck = $this->pollDisbursement($code, $timeout, $poll);

        $reconciliation = DisbursementReconciliation::query()
            ->where('voucher_code', $code)
            ->latest('id')
            ->first();

        return $this->renderResult([
            'scenario' => $scenarioKey,
            'label' => $scenario['label'] ?? $scenarioKey,
            'issuer' => $this->formatUserSummary($issuer),
            'claim_mobile' => $claimMobile,
            'estimate' => $estimate,
            'generated' => $generated->toArray(),
            'claim' => $claim->toArray(),
            'disbursement_check' => $finalCheck,
            'reconciliation' => $reconciliation?->toArray(),
        ]);
    }

    protected function resolveScenario(string $key): ?array
    {
        $scenarios = (array) config('x-change.lifecycle.scenarios', []);

        return $scenarios[$key] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildLifecycleInput(array $scenario, int $issuerId, int $walletId, float $amount): array
    {
        return [
            'issuer_id' => $issuerId,
            'wallet_id' => $walletId,

            'cash' => [
                'amount' => $amount,
                'currency' => $scenario['currency'] ?? 'PHP',
                'validation' => [
                    'secret' => data_get($scenario, 'cash.validation.secret'),
                    'mobile' => data_get($scenario, 'cash.validation.mobile'),
                    'payable' => data_get($scenario, 'cash.validation.payable'),
                    'country' => data_get($scenario, 'cash.validation.country', 'PH'),
                    'location' => data_get($scenario, 'cash.validation.location'),
                    'radius' => data_get($scenario, 'cash.validation.radius'),
                    'mobile_verification' => data_get($scenario, 'cash.validation.mobile_verification'),
                ],
                'settlement_rail' => data_get($scenario, 'cash.settlement_rail', 'INSTAPAY'),
                'fee_strategy' => data_get($scenario, 'cash.fee_strategy', 'absorb'),
                'slice_mode' => data_get($scenario, 'cash.slice_mode'),
                'slices' => data_get($scenario, 'cash.slices'),
                'max_slices' => data_get($scenario, 'cash.max_slices'),
                'min_withdrawal' => data_get($scenario, 'cash.min_withdrawal'),
            ],

            'inputs' => [
                'fields' => (array) data_get($scenario, 'inputs.fields', []),
            ],

            'feedback' => [
                'mobile' => data_get($scenario, 'feedback.mobile'),
                'email' => data_get($scenario, 'feedback.email'),
                'webhook' => data_get($scenario, 'feedback.webhook'),
            ],

            'rider' => [
                'message' => data_get($scenario, 'rider.message'),
                'url' => data_get($scenario, 'rider.url'),
                'redirect_timeout' => data_get($scenario, 'rider.redirect_timeout'),
                'splash' => data_get($scenario, 'rider.splash'),
                'splash_timeout' => data_get($scenario, 'rider.splash_timeout'),
                'og_source' => data_get($scenario, 'rider.og_source'),
            ],

            'count' => (int) data_get($scenario, 'count', 1),
            'prefix' => data_get($scenario, 'prefix', 'TEST'),
            'mask' => data_get($scenario, 'mask', '****'),
            'ttl' => data_get($scenario, 'ttl'),
            'validation' => data_get($scenario, 'validation'),
            'metadata' => data_get($scenario, 'metadata'),
            'voucher_type' => data_get($scenario, 'voucher_type'),
            'target_amount' => data_get($scenario, 'target_amount'),
            'rules' => data_get($scenario, 'rules'),

            '_meta' => [
                'idempotency_key' => 'lifecycle-'.(string) str()->uuid(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildClaimPayload(array $scenario, string $mobile): array
    {
        $payload = [
            'mobile' => $mobile,
            'recipient_country' => 'PH',
            'bank_account' => [
                'bank_code' => (string) data_get($scenario, 'bank_code'),
                'account_number' => (string) data_get($scenario, 'account_number'),
            ],
            'inputs' => (array) data_get($scenario, 'claim.inputs', []),
        ];

        if (($amount = data_get($scenario, 'claim.amount')) !== null) {
            $payload['amount'] = (float) $amount;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    protected function pollDisbursement(string $code, int $timeout, int $poll): array
    {
        $start = time();
        $last = [
            'voucher_code' => $code,
            'current_status' => 'unknown',
        ];

        do {
            Artisan::call('xchange:disbursement:check', [
                'code' => $code,
                '--sync' => true,
                '--json' => true,
            ]);

            $output = trim(Artisan::output());
            $decoded = json_decode($output, true);

            if (is_array($decoded)) {
                $last = $decoded;
                $status = $decoded['current_status'] ?? null;

                if (! $this->option('json')) {
                    $elapsed = time() - $start;
                    $this->line(sprintf(
                        '[poll %ss] voucher=%s status=%s provider_tx=%s',
                        $elapsed,
                        $code,
                        $status ?? 'unknown',
                        $decoded['provider_transaction_id'] ?? 'n/a',
                    ));
                }

                if (in_array($status, ['succeeded', 'failed'], true)) {
                    return $decoded;
                }
            } elseif (! $this->option('json')) {
                $elapsed = time() - $start;
                $this->warn("[poll {$elapsed}s] unable to decode disbursement status response");
            }

            sleep($poll);
        } while ((time() - $start) < $timeout);

        $last['current_status'] = $last['current_status'] ?? 'timeout';

        return $last;
    }

    protected function runCheckOnly(string $code): int
    {
        $timeout = (int) ($this->option('timeout') ?: config('x-change.lifecycle.defaults.timeout', 180));
        $poll = (int) ($this->option('poll') ?: config('x-change.lifecycle.defaults.poll', 10));

        if (! $this->option('json')) {
            $this->info("Checking existing voucher: {$code}");
        }

        $payload = $this->pollDisbursement($code, $timeout, $poll);

        return $this->renderResult([
            'mode' => 'check-only',
            'voucher_code' => $code,
            'disbursement_check' => $payload,
        ]);
    }

    protected function renderResult(array $payload): int
    {
        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Lifecycle scenario completed.');
        $this->line('Scenario: '.($payload['scenario'] ?? $payload['mode'] ?? 'n/a'));

        if (isset($payload['issuer'])) {
            $this->line('Issuer: '.($payload['issuer']['email'] ?? ('#'.($payload['issuer']['id'] ?? 'n/a'))));
        }

        if (isset($payload['claim_mobile'])) {
            $this->line('Claim Mobile: '.$payload['claim_mobile']);
        }

        if (isset($payload['generated']['code'])) {
            $this->line('Voucher Code: '.$payload['generated']['code']);
        }

        if (isset($payload['estimate']['total'])) {
            $this->line('Estimated Tariff: '.$payload['estimate']['total']);
        }

        if (isset($payload['disbursement_check']['current_status'])) {
            $this->line('Final Status: '.$payload['disbursement_check']['current_status']);
        }

        return self::SUCCESS;
    }

    protected function assertLifecycleUserModelSupportsMobile(): void
    {
        $class = $this->userModelClass();

        if (! is_subclass_of($class, HasMobileChannel::class)) {
            throw new RuntimeException(sprintf(
                'Configured lifecycle user model [%s] must implement [%s].',
                $class,
                HasMobileChannel::class,
            ));
        }
    }

    protected function userModelClass(): string
    {
        $class = (string) config('x-change.lifecycle.defaults.user_model', \App\Models\User::class);

        if ($class === '' || ! class_exists($class)) {
            throw new RuntimeException('Configured lifecycle user model is invalid.');
        }

        return $class;
    }

    protected function resolveIssuerModel(int $issuerId): Model
    {
        $class = $this->userModelClass();

        /** @var Model|null $issuer */
        $issuer = $class::query()->find($issuerId);

        if (! $issuer) {
            throw new RuntimeException(sprintf(
                'Unable to resolve lifecycle issuer [%s] using model [%s].',
                $issuerId,
                $class,
            ));
        }

        if (! $issuer instanceof HasMobileChannel) {
            throw new RuntimeException(sprintf(
                'Lifecycle issuer model [%s] must implement [%s].',
                $class,
                HasMobileChannel::class,
            ));
        }

        return $issuer;
    }

    protected function resolveScenarioMobile(array $scenario, Model $issuer): string
    {
        $mobile = (string) (
        data_get($scenario, 'mobile')
            ?: ($issuer instanceof HasMobileChannel ? $issuer->getMobileChannel() : null)
            ?: ''
        );

        if ($mobile === '') {
            throw new RuntimeException(
                'Lifecycle scenario requires a mobile number either in scenario config or on the issuer mobile channel.'
            );
        }

        return $mobile;
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatUserSummary(Model $user): array
    {
        return [
            'id' => $user->getKey(),
            'email' => $user->getAttribute('email'),
            'mobile' => $user instanceof HasMobileChannel ? $user->getMobileChannel() : null,
        ];
    }
}
