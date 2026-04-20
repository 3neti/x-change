<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Lifecycle;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Number;
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
      {scenario? : Scenario key from x-change.lifecycle.scenarios}
        {--list : List available scenarios}
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
        {--max-polls= : Maximum number of polling attempts}
        {--accept-pending : Treat a trusted pending provider transaction as good enough}
        {--only-attempt= : Run only one named attempt from the scenario}
        {--json : Output JSON}';

    protected $description = 'Run a named lifecycle scenario.';

    public function handle(
        EstimatePayCodeCost $estimatePayCodeCost,
        GeneratePayCode $generatePayCode,
        SubmitPayCodeClaim $submitPayCodeClaim,
        VoucherAccessContract $vouchers,
    ): int {
        if ($this->option('list')) {
            return $this->listScenarios();
        }
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

        $defaults = (array) config('x-change.lifecycle.defaults', []);
        $scenario = array_replace_recursive($defaults, $scenario);
        $attempts = $this->normalizeScenarioAttempts($scenario);
        try {
            $attempts = $this->filterAttemptsForOption($attempts);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $issuerId = (int) ($this->option('issuer') ?: $scenario['issuer_id'] ?? 1);
        $walletId = (int) ($this->option('wallet') ?: $scenario['wallet_id'] ?? 1);
        $amount = (float) ($this->option('amount') ?: $scenario['amount'] ?? 25);
        $timeout = (int) ($this->option('timeout') ?: $scenario['timeout'] ?? 180);
        $poll = max(1, (int) ($this->option('poll') ?: $scenario['poll'] ?? 10));
        $maxPolls = $this->resolveMaxPolls($timeout, $poll);

        $issuer = $this->resolveIssuerModel($issuerId);
        $baseClaimMobile = $this->resolveScenarioMobile($scenario, $issuer);
        $idempotencyKey = 'lifecycle-'.(string) str()->uuid();

        if (! $this->option('json')) {
            $this->info("Running scenario: {$scenarioKey}");

            if (is_string($this->option('only-attempt')) && trim((string) $this->option('only-attempt')) !== '') {
                $this->line('Selected attempt: '.$this->option('only-attempt'));
            }

            $this->line('Estimating cost...');
        }

        $lifecycleInput = $this->buildLifecycleInput($scenario, $issuerId, $walletId, $amount, $idempotencyKey);
        $estimate = $estimatePayCodeCost->handle($lifecycleInput)->toArray();

        if (! $this->option('json')) {
            $this->renderEstimateSummary($estimate);
            $this->line('Generating voucher...');
        }

        $generated = $generatePayCode->handle($lifecycleInput);
        $code = $generated->code;

        if ($this->option('no-claim')) {
            $walletTransactions = $this->recentWalletTransactions(
                issuer: $issuer,
                idempotencyKey: $idempotencyKey,
                voucherCode: null,
                limit: 10,
            );

            return $this->renderResult([
                'scenario' => $scenarioKey,
                'label' => $scenario['label'] ?? $scenarioKey,
                'selected_attempt' => $this->option('only-attempt'),
                'issuer' => $this->formatUserSummary($issuer),
                'claim_mobile' => $baseClaimMobile,
                'attempts' => array_keys($attempts),
                'attempt_summary' => [
                    'passed' => 0,
                    'failed' => 0,
                    'total' => count($attempts),
                ],
                'estimate' => $estimate,
                'generated' => $generated->toArray(),
                'wallet_transactions' => $walletTransactions,
            ]);
        }

        $voucher = $vouchers->findByCodeOrFail($code);

        $attemptResults = [];
        $commandStatus = self::SUCCESS;

        foreach ($attempts as $attemptKey => $attempt) {
            $attemptMobile = $this->resolveAttemptMobile($scenario, $attempt, $baseClaimMobile);

            if (! $this->option('json')) {
                $this->line(sprintf(
                    'Submitting claim for voucher %s (attempt: %s)...',
                    $code,
                    $attemptKey
                ));
            }

            $claimPayload = $this->buildClaimPayload($scenario, $attempt, $attemptMobile);

            try {
                $claim = $submitPayCodeClaim->handle($voucher, $claimPayload);

                if (! $this->option('json')) {
                    $this->line('Polling disbursement status...');
                }

                $finalCheck = $this->pollDisbursement(
                    code: $code,
                    timeout: $timeout,
                    poll: $poll,
                    maxPolls: $maxPolls,
                    acceptPending: (bool) $this->option('accept-pending'),
                );

                $actual = [
                    'status' => 'succeeded',
                    'message' => $this->resolveSuccessMessage($claim, $finalCheck),
                    'claim' => $claim->toArray(),
                    'disbursement_check' => $finalCheck,
                ];
            } catch (\Throwable $e) {
                $actual = [
                    'status' => 'failed',
                    'message' => $e->getMessage(),
                    'error' => [
                        'class' => $e::class,
                        'message' => $e->getMessage(),
                    ],
                ];
            }

            $evaluation = $this->evaluateAttemptExpectation($attempt, $actual);

            $attemptResults[$attemptKey] = [
                'claim_mobile' => $attemptMobile,
                'claim_payload' => $claimPayload,
                'expect' => $attempt['expect'] ?? [],
                'actual' => $actual,
                'evaluation' => $evaluation,
                'status' => $actual['status'],
                'message' => $actual['message'],
                'claim' => $actual['claim'] ?? null,
                'disbursement_check' => $actual['disbursement_check'] ?? null,
                'error' => $actual['error'] ?? null,
            ];

            if (! $evaluation['passed']) {
                $commandStatus = self::FAILURE;
            }

            if (! $this->option('json')) {
                $this->renderAttemptEvaluation($attemptKey, $evaluation, $actual);
            }
        }

        $reconciliation = DisbursementReconciliation::query()
            ->where('voucher_code', $code)
            ->latest('id')
            ->first();

        $walletTransactions = $this->recentWalletTransactions(
            issuer: $issuer,
            idempotencyKey: $idempotencyKey,
            voucherCode: $code,
            limit: 10,
        );

        $attemptSummary = $this->summarizeAttempts($attemptResults);

        if (! $this->option('json')) {
            $this->renderAttemptsSummary($attemptSummary);
        }

        $this->renderResult([
            'scenario' => $scenarioKey,
            'label' => $scenario['label'] ?? $scenarioKey,
            'selected_attempt' => $this->option('only-attempt'),
            'issuer' => $this->formatUserSummary($issuer),
            'claim_mobile' => $baseClaimMobile,
            'attempts' => $attemptResults,
            'attempt_summary' => $attemptSummary,
            'estimate' => $estimate,
            'generated' => $generated->toArray(),
            'reconciliation' => $reconciliation?->toArray(),
            'wallet_transactions' => $walletTransactions,
        ]);

        return $commandStatus;
    }

    protected function listScenarios(): int
    {
        $scenarios = config('x-change.lifecycle.scenarios', []);

        if (empty($scenarios)) {
            $this->warn('No lifecycle scenarios found.');

            return self::SUCCESS;
        }

        $this->info('Available lifecycle scenarios:');

        foreach ($scenarios as $key => $scenario) {
            $label = $scenario['label'] ?? $key;
            $this->line(sprintf(' - %s (%s)', $key, $label));
        }

        return self::SUCCESS;
    }

    protected function resolveScenario(string $key): ?array
    {
        $scenarios = (array) config('x-change.lifecycle.scenarios', []);

        return $scenarios[$key] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildLifecycleInput(
        array $scenario,
        int $issuerId,
        int $walletId,
        float $amount,
        string $idempotencyKey,
    ): array {
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

            'starts_at' => data_get($scenario, 'starts_at'),
            'expires_at' => data_get($scenario, 'expires_at'),

            'validation' => data_get($scenario, 'validation'),
            'metadata' => data_get($scenario, 'metadata'),
            'voucher_type' => data_get($scenario, 'voucher_type'),
            'target_amount' => data_get($scenario, 'target_amount'),
            'rules' => data_get($scenario, 'rules'),

            '_meta' => [
                'idempotency_key' => $idempotencyKey,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildClaimPayload(array $scenario, array $attempt, string $mobile): array
    {
        $payload = array_replace_recursive(
            [
                'mobile' => $mobile,
                'recipient_country' => 'PH',
                'bank_account' => [
                    'bank_code' => (string) data_get($scenario, 'bank_code'),
                    'account_number' => (string) data_get($scenario, 'account_number'),
                ],
                'inputs' => [],
            ],
            (array) data_get($scenario, 'claim', []),
            (array) data_get($attempt, 'claim', []),
        );

        if (data_get($payload, 'amount') !== null) {
            $payload['amount'] = (float) data_get($payload, 'amount');
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    protected function pollDisbursement(
        string $code,
        int $timeout,
        int $poll,
        ?int $maxPolls = null,
        bool $acceptPending = false,
    ): array {
        $start = time();
        $attempt = 0;
        $last = [
            'voucher_code' => $code,
            'current_status' => 'unknown',
        ];

        do {
            $attempt++;

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
                $providerTransactionId = $decoded['provider_transaction_id'] ?? null;
                $needsReview = (bool) ($decoded['needs_review'] ?? false);
                $provider = $decoded['provider'] ?? null;

                if (! $this->option('json')) {
                    $elapsed = time() - $start;
                    $maxPollsLabel = $maxPolls !== null ? (string) $maxPolls : '∞';

                    $this->line(sprintf(
                        '[poll %d/%s | %ss] status=%s provider_tx=%s needs_review=%s',
                        $attempt,
                        $maxPollsLabel,
                        $elapsed,
                        $status ?? 'unknown',
                        $providerTransactionId ?: 'n/a',
                        $needsReview ? 'yes' : 'no',
                    ));
                }

                if (in_array($status, ['succeeded', 'failed'], true)) {
                    return $decoded;
                }

                if (
                    $acceptPending
                    && $status === 'pending'
                    && ! $needsReview
                    && is_string($provider) && $provider !== ''
                    && is_string($providerTransactionId) && $providerTransactionId !== ''
                ) {
                    if (! $this->option('json')) {
                        $this->info('Trusted pending transaction accepted as good enough.');
                    }

                    return $decoded;
                }
            } elseif (! $this->option('json')) {
                $elapsed = time() - $start;
                $this->warn("[poll {$elapsed}s] unable to decode disbursement status response");
            }

            if ($maxPolls !== null && $attempt >= $maxPolls) {
                break;
            }

            if ((time() - $start) >= $timeout) {
                break;
            }

            sleep($poll);
        } while (true);

        $last['current_status'] = $last['current_status'] ?? 'timeout';
        $last['timed_out'] = true;
        $last['poll_attempts'] = $attempt;

        return $last;
    }

    protected function runCheckOnly(string $code): int
    {
        $timeout = (int) ($this->option('timeout') ?: config('x-change.lifecycle.defaults.timeout', 180));
        $poll = max(1, (int) ($this->option('poll') ?: config('x-change.lifecycle.defaults.poll', 10)));
        $maxPolls = $this->resolveMaxPolls($timeout, $poll);

        if (! $this->option('json')) {
            $this->info("Checking existing voucher: {$code}");
        }

        $payload = $this->pollDisbursement(
            code: $code,
            timeout: $timeout,
            poll: $poll,
            maxPolls: $maxPolls,
            acceptPending: (bool) $this->option('accept-pending'),
        );

        return $this->renderResult([
            'mode' => 'check-only',
            'voucher_code' => $code,
            'disbursement_check' => $payload,
        ]);
    }

    protected function renderResult(array $payload): int
    {
        if ($this->option('json')) {
            $payload = $this->normalizePayloadForJson($payload);
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Lifecycle scenario completed.');
        $this->line('Scenario: '.($payload['scenario'] ?? $payload['mode'] ?? 'n/a'));

        if (isset($payload['issuer'])) {
            $issuerLabel = $payload['issuer']['email'] ?? ('#'.($payload['issuer']['id'] ?? 'n/a'));
            $issuerMobile = $payload['issuer']['mobile'] ?? 'n/a';
            $this->line("Issuer: {$issuerLabel} / {$issuerMobile}");
        }

        if (isset($payload['claim_mobile'])) {
            $this->line('Claim Mobile: '.$payload['claim_mobile']);
        }

        if (isset($payload['generated']['code'])) {
            $this->line('Voucher Code: '.$payload['generated']['code']);
        }

        if (isset($payload['attempt_summary'])) {
            $summary = $payload['attempt_summary'];
            $this->line(sprintf(
                'Attempts: %d/%d passed',
                (int) ($summary['passed'] ?? 0),
                (int) ($summary['total'] ?? 0),
            ));
        }

        if (isset($payload['estimate'])) {
            $this->renderEstimateSummary($payload['estimate']);
        }

        if (isset($payload['generated']['wallet']['balance_before'], $payload['generated']['wallet']['balance_after'])) {
            $this->line(sprintf(
                'Wallet Balance: %s → %s',
                Number::currency(((float) $payload['generated']['wallet']['balance_before']) / 100, in: 'PHP'),
                Number::currency(((float) $payload['generated']['wallet']['balance_after']) / 100, in: 'PHP'),
            ));
        }

        if (! empty($payload['wallet_transactions']) && is_array($payload['wallet_transactions'])) {
            $this->newLine();
            $this->line('Recent Wallet Transactions:');
            $this->renderWalletTransactionsTable($payload['wallet_transactions']);
        }

        if (isset($payload['disbursement_check']['current_status'])) {
            $this->line('Final Status: '.$payload['disbursement_check']['current_status']);
        }

        if (isset($payload['disbursement_check']['provider_transaction_id'])) {
            $this->line('Provider Transaction ID: '.($payload['disbursement_check']['provider_transaction_id'] ?: 'n/a'));
        }

        if (! empty($payload['disbursement_check']['timed_out'])) {
            $this->warn('Polling stopped before a terminal status was reached.');
        }

        return self::SUCCESS;
    }

    protected function renderEstimateSummary(array $estimate): void
    {
        $currency = (string) ($estimate['currency'] ?? 'PHP');

        if (isset($estimate['total'])) {
            $this->line('Estimated Tariff: '.Number::currency((float) $estimate['total'], in: $currency));
        }

        $charges = $estimate['charges'] ?? null;

        if (! is_array($charges) || $charges === []) {
            return;
        }

        $this->line('Charge Lines:');

        foreach ($charges as $charge) {
            $label = (string) ($charge['label'] ?? $charge['index'] ?? 'Unknown');
            $quantity = (int) ($charge['quantity'] ?? 1);
            $unitPrice = (float) ($charge['unit_price'] ?? 0);
            $price = (float) ($charge['price'] ?? 0);
            $chargeCurrency = (string) ($charge['currency'] ?? $currency);

            $this->line(sprintf(
                '  - %s | %s × %d = %s',
                $label,
                Number::currency($unitPrice, in: $chargeCurrency),
                $quantity,
                Number::currency($price, in: $chargeCurrency),
            ));
        }
    }

    protected function resolveMaxPolls(int $timeout, int $poll): ?int
    {
        $configured = $this->option('max-polls');

        if ($configured !== null && $configured !== '') {
            return max(1, (int) $configured);
        }

        return (int) ceil($timeout / max(1, $poll));
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
        $class = (string) config('x-change.lifecycle.defaults.user_model', User::class);

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

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function recentWalletTransactions(
        Model $issuer,
        string $idempotencyKey,
        ?string $voucherCode = null,
        int $limit = 10,
    ): array {
        if (! isset($issuer->wallet) || ! $issuer->wallet) {
            return [];
        }

        $wallet = $issuer->wallet;

        if (! method_exists($wallet, 'transactions')) {
            return [];
        }

        $transactions = $wallet->transactions()
            ->latest('id')
            ->limit(max($limit, 1) * 5)
            ->get();

        return $transactions
            ->filter(function ($transaction) use ($idempotencyKey, $voucherCode) {
                $meta = $this->normalizeTransactionMeta(
                    $transaction->meta ?? $transaction->metadata ?? null
                );

                if (data_get($meta, 'idempotency_key') === $idempotencyKey) {
                    return true;
                }

                if ($voucherCode !== null && $voucherCode !== '') {
                    return data_get($meta, 'voucher_code') === $voucherCode
                        || data_get($meta, 'external_code') === $voucherCode
                        || data_get($meta, 'code') === $voucherCode;
                }

                return false;
            })
            ->take($limit)
            ->map(function ($transaction): array {
                $amountMinor = $this->resolveTransactionAmountMinor($transaction);
                $currency = (string) ($transaction->currency ?? 'PHP');
                $meta = $this->normalizeTransactionMeta(
                    $transaction->meta ?? $transaction->metadata ?? null
                );

                return [
                    'id' => $transaction->id ?? null,
                    'uuid' => $transaction->uuid ?? null,
                    'type' => $transaction->type ?? $transaction->transaction_type ?? null,
                    'confirmed' => isset($transaction->confirmed)
                        ? (bool) $transaction->confirmed
                        : null,
                    'amount_minor' => $amountMinor,
                    'amount' => $amountMinor / 100,
                    'currency' => $currency,
                    'formatted_amount' => Number::currency($amountMinor / 100, in: $currency),
                    'reason' => data_get($meta, 'reason'),
                    'voucher_code' => data_get($meta, 'voucher_code')
                        ?? data_get($meta, 'external_code')
                            ?? data_get($meta, 'code'),
                    'reference' => data_get($meta, 'reference'),
                    'idempotency_key' => data_get($meta, 'idempotency_key'),
                    'created_at' => optional($transaction->created_at)?->toIso8601String(),
                    'meta' => $meta,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $transactions
     */
    protected function renderWalletTransactionsTable(array $transactions): void
    {
        $rows = array_map(function (array $tx): array {
            return [
                $tx['id'] ?? 'n/a',
                $tx['type'] ?? 'n/a',
                $tx['formatted_amount'] ?? Number::currency((float) ($tx['amount'] ?? 0), in: (string) ($tx['currency'] ?? 'PHP')),
                $tx['reason'] ?? 'n/a',
                $tx['voucher_code'] ?? 'n/a',
                $tx['idempotency_key'] ?? 'n/a',
                $tx['created_at'] ?? 'n/a',
            ];
        }, $transactions);

        $this->table(
            ['ID', 'Type', 'Amount', 'Reason', 'Voucher', 'Idempotency Key', 'Created At'],
            $rows
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function normalizeTransactionMeta(mixed $meta): array
    {
        if (is_array($meta)) {
            return $meta;
        }

        if (is_string($meta) && $meta !== '') {
            $decoded = json_decode($meta, true);

            return is_array($decoded) ? $decoded : [];
        }

        if (is_object($meta)) {
            return (array) $meta;
        }

        return [];
    }

    protected function resolveTransactionAmountMinor(object $transaction): int
    {
        $candidates = [
            $transaction->amount ?? null,
            $transaction->amount_int ?? null,
            $transaction->amount_minor ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_numeric($candidate)) {
                return (int) $candidate;
            }
        }

        return 0;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function normalizeScenarioAttempts(array $scenario): array
    {
        $attempts = data_get($scenario, 'attempts');

        if (is_array($attempts) && $attempts !== []) {
            return $attempts;
        }

        return [
            'default' => [
                'claim' => (array) data_get($scenario, 'claim', []),
                'expect' => (array) data_get($scenario, 'expect', []),
            ],
        ];
    }

    protected function resolveAttemptMobile(array $scenario, array $attempt, string $defaultMobile): string
    {
        $mobile = (string) (
            data_get($attempt, 'claim.mobile')
                ?: data_get($scenario, 'claim.mobile')
                ?: $defaultMobile
        );

        if ($mobile === '') {
            throw new RuntimeException('Lifecycle attempt mobile could not be resolved.');
        }

        return $mobile;
    }

    protected function expectedAttemptStatus(array $attempt, string $default = 'succeeded'): string
    {
        return (string) data_get($attempt, 'expect.status', $default);
    }

    /**
     * @param  array<string, mixed>  $attempt
     * @param  array<string, mixed>  $actual
     * @return array<string, mixed>
     */
    protected function evaluateAttemptExpectation(array $attempt, array $actual): array
    {
        $expectedStatus = $this->expectedAttemptStatus($attempt);
        $actualStatus = (string) ($actual['status'] ?? 'failed');
        $actualMessage = (string) ($actual['message'] ?? '');

        $checks = [];

        $statusPassed = $expectedStatus === $actualStatus;
        $checks['status'] = [
            'passed' => $statusPassed,
            'expected' => $expectedStatus,
            'actual' => $actualStatus,
        ];

        $messageNeedles = array_values(array_filter(
            (array) data_get($attempt, 'expect.message_contains', []),
            fn ($value) => is_string($value) && trim($value) !== ''
        ));

        $messagePassed = true;
        $missingNeedles = [];

        if ($messageNeedles !== []) {
            $haystack = mb_strtolower($actualMessage);

            foreach ($messageNeedles as $needle) {
                if (! str_contains($haystack, mb_strtolower($needle))) {
                    $messagePassed = false;
                    $missingNeedles[] = $needle;
                }
            }
        }

        $checks['message_contains'] = [
            'passed' => $messagePassed,
            'expected' => $messageNeedles,
            'actual' => $actualMessage,
            'missing' => $missingNeedles,
        ];

        $passed = collect($checks)->every(fn (array $check) => (bool) ($check['passed'] ?? false));

        return [
            'passed' => $passed,
            'checks' => $checks,
            'summary' => $this->summarizeEvaluation($passed, $statusPassed, $messagePassed, $expectedStatus, $actualStatus),
        ];
    }

    protected function summarizeEvaluation(
        bool $passed,
        bool $statusPassed,
        bool $messagePassed,
        string $expectedStatus,
        string $actualStatus,
    ): string {
        if ($passed && $actualStatus === 'failed') {
            return 'FAILED as expected';
        }

        if ($passed && $actualStatus === 'succeeded') {
            return 'SUCCEEDED as expected';
        }

        if (! $statusPassed && $expectedStatus === 'failed' && $actualStatus === 'succeeded') {
            return 'UNEXPECTED SUCCESS';
        }

        if (! $statusPassed && $expectedStatus === 'succeeded' && $actualStatus === 'failed') {
            return 'UNEXPECTED FAILURE';
        }

        if (! $messagePassed) {
            return 'STATUS matched, message check failed';
        }

        return 'Expectation mismatch';
    }

    /**
     * @param  mixed  $claim
     * @param  array<string, mixed>  $finalCheck
     */
    protected function resolveSuccessMessage($claim, array $finalCheck): string
    {
        $status = (string) data_get($finalCheck, 'status', '');

        if ($status !== '') {
            return "Disbursement status: {$status}";
        }

        return 'Claim submitted successfully.';
    }

    /**
     * @param  array<string, mixed>  $evaluation
     * @param  array<string, mixed>  $actual
     */
    protected function renderAttemptEvaluation(string $attemptKey, array $evaluation, array $actual): void
    {
        $summary = (string) data_get($evaluation, 'summary', 'Unknown');

        if ((bool) data_get($evaluation, 'passed', false)) {
            $this->info(sprintf('Attempt [%s]: %s', $attemptKey, $summary));
        } else {
            $this->error(sprintf('Attempt [%s]: %s', $attemptKey, $summary));
        }

        $checks = (array) data_get($evaluation, 'checks', []);

        $statusCheck = (array) ($checks['status'] ?? []);
        $messageCheck = (array) ($checks['message_contains'] ?? []);

        $this->line(sprintf(
            '  Status check: expected=%s actual=%s',
            $statusCheck['expected'] ?? 'n/a',
            $statusCheck['actual'] ?? 'n/a',
        ));

        $expectedFragments = (array) ($messageCheck['expected'] ?? []);
        if ($expectedFragments !== []) {
            $this->line(sprintf(
                '  Message check: %s',
                (bool) ($messageCheck['passed'] ?? false)
                    ? 'matched'
                    : 'missing ['.implode(', ', (array) ($messageCheck['missing'] ?? [])).']'
            ));
        }

        $actualMessage = (string) ($actual['message'] ?? '');
        if ($actualMessage !== '') {
            $this->line('  Actual message: '.$actualMessage);
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $attempts
     * @return array<string, array<string, mixed>>
     */
    protected function filterAttemptsForOption(array $attempts): array
    {
        $onlyAttempt = $this->option('only-attempt');

        if (! is_string($onlyAttempt) || trim($onlyAttempt) === '') {
            return $attempts;
        }

        $onlyAttempt = trim($onlyAttempt);

        if (! array_key_exists($onlyAttempt, $attempts)) {
            throw new RuntimeException(sprintf(
                'Unknown attempt [%s]. Available attempts: %s',
                $onlyAttempt,
                implode(', ', array_keys($attempts))
            ));
        }

        return [
            $onlyAttempt => $attempts[$onlyAttempt],
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $attemptResults
     * @return array<string, int>
     */
    protected function summarizeAttempts(array $attemptResults): array
    {
        $total = count($attemptResults);
        $passed = collect($attemptResults)
            ->filter(fn (array $result) => (bool) data_get($result, 'evaluation.passed', false))
            ->count();

        $failed = $total - $passed;

        return [
            'passed' => $passed,
            'failed' => $failed,
            'total' => $total,
        ];
    }

    /**
     * @param  array<string, int>  $summary
     */
    protected function renderAttemptsSummary(array $summary): void
    {
        $this->newLine();
        $this->info('Attempts Summary:');
        $this->line('  Passed: '.$summary['passed']);
        $this->line('  Failed: '.$summary['failed']);
        $this->line('  Total: '.$summary['total']);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function normalizePayloadForJson(array $payload): array
    {
        $attempts = data_get($payload, 'attempts');

        if (is_array($attempts) && ! array_is_list($attempts)) {
            $payload['attempts'] = collect($attempts)
                ->map(function (array $attempt, string $name) {
                    return array_merge(['name' => $name], $attempt);
                })
                ->values()
                ->all();
        }

        return $payload;
    }
}
