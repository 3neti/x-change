<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Lifecycle;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Number;
use InvalidArgumentException;
use LBHurtado\ModelChannel\Contracts\HasMobileChannel;
use LBHurtado\XChange\Actions\PayCode\EstimatePayCodeCost;
use LBHurtado\XChange\Actions\PayCode\GeneratePayCode;
use LBHurtado\XChange\Actions\Redemption\SubmitPayCodeClaim;
use LBHurtado\XChange\Console\Commands\Lifecycle\ScenarioRunners\ScenarioRunContext;
use LBHurtado\XChange\Console\Commands\Lifecycle\ScenarioRunners\ScenarioRunnerRegistry;
use LBHurtado\XChange\Console\Commands\Lifecycle\ScenarioRunners\Support\LifecycleResultRenderer;
use LBHurtado\XChange\Console\Commands\Lifecycle\ScenarioRunners\Support\LifecycleScenarioBootstrapper;
use LBHurtado\XChange\Console\Commands\Lifecycle\ScenarioRunners\Support\LifecycleScenarioRepository;
use LBHurtado\XChange\Console\Commands\Lifecycle\ScenarioRunners\Support\LifecycleUserSummary;
use LBHurtado\XChange\Console\Commands\Lifecycle\ScenarioRunners\Support\WalletTransactionSnapshot;
use LBHurtado\XChange\Contracts\SettlementEnvelopeReadinessContract;
use LBHurtado\XChange\Contracts\VoucherAccessContract;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Models\VoucherClaim;
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
        SubmitPayCodeClaim $submitPayCodeClaim,
        SettlementEnvelopeReadinessContract $settlementEnvelopeReadiness,
        LifecycleScenarioRepository $scenarioRepository,
        LifecycleScenarioBootstrapper $bootstrapper,
        LifecycleResultRenderer $renderer,
    ): int {
        if ($this->option('list')) {
            return $this->listScenarios($scenarioRepository);
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
            return $this->runCheckOnly(trim($existingCode), $renderer);
        }

        $scenarioKey = (string) $this->argument('scenario');

        try {
            $scenario = $scenarioRepository->findOrFail($scenarioKey);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->assertLifecycleUserModelSupportsMobile();

        $defaults = (array) config('x-change.lifecycle.defaults', []);
        $scenario = array_replace_recursive($defaults, $scenario);

        $claims = $this->normalizeScenarioClaims($scenario);

        if ($claims !== null) {
            if (! $this->option('json')) {
                $this->info("Running scenario: {$scenarioKey}");
                $this->line('Estimating cost...');
            }

            $bootstrap = $bootstrapper->bootstrap(
                scenario: $scenario,
                issuerOption: is_string($this->option('issuer')) ? (string) $this->option('issuer') : null,
                walletOption: is_string($this->option('wallet')) ? (string) $this->option('wallet') : null,
                amountOption: is_string($this->option('amount')) ? (string) $this->option('amount') : null,
                timeoutOption: is_string($this->option('timeout')) ? (string) $this->option('timeout') : null,
                pollOption: is_string($this->option('poll')) ? (string) $this->option('poll') : null,
                maxPollsOption: is_string($this->option('max-polls')) ? (string) $this->option('max-polls') : null,
            );

            if (! $this->option('json')) {
                $this->renderEstimateSummary($bootstrap->estimate);
                $this->line('Generating voucher...');
            }

            return $this->runSequentialClaimsScenario(
                scenarioKey: $scenarioKey,
                scenario: $scenario,
                issuer: $bootstrap->issuer,
                generated: $bootstrap->generated,
                voucher: $bootstrap->voucher,
                claims: $claims,
                baseClaimMobile: $bootstrap->baseClaimMobile,
                timeout: $bootstrap->timeout,
                poll: $bootstrap->poll,
                maxPolls: $bootstrap->maxPolls,
                idempotencyKey: $bootstrap->idempotencyKey,
                submitPayCodeClaim: $submitPayCodeClaim,
                renderer: $renderer,
            );
        }

        try {
            $attempts = $scenarioRepository->attemptsFor(
                scenario: $scenario,
                selectedAttempt: is_string($this->option('only-attempt'))
                    ? (string) $this->option('only-attempt')
                    : null,
            );
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if (! $this->option('json')) {
            $this->info("Running scenario: {$scenarioKey}");

            if (is_string($this->option('only-attempt')) && trim((string) $this->option('only-attempt')) !== '') {
                $this->line('Selected attempt: '.$this->option('only-attempt'));
            }

            $this->line('Estimating cost...');
        }

        $bootstrap = $bootstrapper->bootstrap(
            scenario: $scenario,
            issuerOption: is_string($this->option('issuer')) ? (string) $this->option('issuer') : null,
            walletOption: is_string($this->option('wallet')) ? (string) $this->option('wallet') : null,
            amountOption: is_string($this->option('amount')) ? (string) $this->option('amount') : null,
            timeoutOption: is_string($this->option('timeout')) ? (string) $this->option('timeout') : null,
            pollOption: is_string($this->option('poll')) ? (string) $this->option('poll') : null,
            maxPollsOption: is_string($this->option('max-polls')) ? (string) $this->option('max-polls') : null,
        );

        if (! $this->option('json')) {
            $this->renderEstimateSummary($bootstrap->estimate);
            $this->line('Generating voucher...');
        }

        $code = $bootstrap->generated->code;

        if ($this->option('no-claim')) {
            $walletTransactions = app(WalletTransactionSnapshot::class)->recentFor(
                issuer: $bootstrap->issuer,
                idempotencyKey: $bootstrap->idempotencyKey,
                voucherCode: $bootstrap->generated->code,
                limit: 10,
            );

            return $renderer->render(
                command: $this,
                payload: [
                    'scenario' => $scenarioKey,
                    'label' => $scenario['label'] ?? $scenarioKey,
                    'selected_attempt' => $this->option('only-attempt'),
                    'issuer' => app(LifecycleUserSummary::class)->fromModel($bootstrap->issuer),
                    'claim_mobile' => $bootstrap->baseClaimMobile,
                    'attempts' => array_keys($attempts),
                    'attempt_summary' => [
                        'passed' => 0,
                        'failed' => 0,
                        'total' => count($attempts),
                    ],
                    'estimate' => $bootstrap->estimate,
                    'generated' => $bootstrap->generated->toArray(),
                    'wallet_transactions' => $walletTransactions,
                ],
            );
        }

        $voucher = $bootstrap->voucher;

        $registry = app(ScenarioRunnerRegistry::class);

        $mode = $scenarioRepository->modeFor($scenario);

        if ($registry->has($mode)) {
            $result = $registry->for($mode)->run(
                new ScenarioRunContext(
                    command: $this,
                    scenarioKey: $scenarioKey,
                    scenario: $scenario,
                    issuer: $bootstrap->issuer,
                    generated: $bootstrap->generated,
                    voucher: $bootstrap->voucher,
                    attempts: $attempts,
                    baseClaimMobile: $bootstrap->baseClaimMobile,
                    estimate: $bootstrap->estimate,
                    idempotencyKey: $bootstrap->idempotencyKey,
                    readiness: $settlementEnvelopeReadiness,
                )
            );

            return $renderer->render(
                command: $this,
                payload: $result->payload,
                exitCode: $result->exitCode,
            );
        }

        $attemptResults = [];
        $commandStatus = self::SUCCESS;

        foreach ($attempts as $attemptKey => $attempt) {
            $attemptMobile = $this->resolveAttemptMobile($scenario, $attempt, $bootstrap->baseClaimMobile);

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
                    timeout: $bootstrap->timeout,
                    poll: $bootstrap->poll,
                    maxPolls: $bootstrap->maxPolls,
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

        $walletTransactions = app(WalletTransactionSnapshot::class)->recentFor(
            issuer: $bootstrap->issuer,
            idempotencyKey: $bootstrap->idempotencyKey,
            voucherCode: $bootstrap->generated->code,
            limit: 10,
        );

        $attemptSummary = $this->summarizeAttempts($attemptResults);

        if (! $this->option('json')) {
            $this->renderAttemptsSummary($attemptSummary);
        }

        return $renderer->render(
            command: $this,
            payload: [
                'scenario' => $scenarioKey,
                'label' => $scenario['label'] ?? $scenarioKey,
                'selected_attempt' => $this->option('only-attempt'),
                'issuer' => app(LifecycleUserSummary::class)->fromModel($bootstrap->issuer),
                'claim_mobile' => $bootstrap->baseClaimMobile,
                'attempts' => $attemptResults,
                'attempt_summary' => $attemptSummary,
                'estimate' => $bootstrap->estimate,
                'generated' => $bootstrap->generated->toArray(),
                'reconciliation' => $reconciliation?->toArray(),
                'wallet_transactions' => $walletTransactions,
            ],
            exitCode: $commandStatus,
        );
    }

    protected function listScenarios(LifecycleScenarioRepository $scenarioRepository): int
    {
        $scenarios = $scenarioRepository->all();

        if ($scenarios === []) {
            $this->warn('No lifecycle scenarios found.');

            return self::SUCCESS;
        }

        $this->info('Available lifecycle scenarios:');

        foreach ($scenarios as $key => $scenario) {
            $this->line(sprintf(
                ' - %s (%s)',
                $key,
                $scenarioRepository->labelFor((string) $key, (array) $scenario),
            ));
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

    protected function runCheckOnly(string $code, LifecycleResultRenderer $renderer): int
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

        return $renderer->render(
            command: $this,
            payload: [
                'mode' => 'check-only',
                'voucher_code' => $code,
                'disbursement_check' => $payload,
            ],
        );
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

    /**
     * @return array<string, array<string, mixed>>|null
     */
    protected function normalizeScenarioClaims(array $scenario): ?array
    {
        $claims = data_get($scenario, 'claims');

        if (! is_array($claims) || $claims === []) {
            return null;
        }

        return $claims;
    }

    protected function runSequentialClaimsScenario(
        string $scenarioKey,
        array $scenario,
        Model $issuer,
        $generated,
        $voucher,
        array $claims,
        string $baseClaimMobile,
        int $timeout,
        int $poll,
        int $maxPolls,
        string $idempotencyKey,
        SubmitPayCodeClaim $submitPayCodeClaim,
        LifecycleResultRenderer $renderer,
    ): int {
        $claimResults = [];
        $commandStatus = self::SUCCESS;

        foreach ($claims as $claimKey => $claimStep) {
            $voucher = $voucher->fresh();

            $claimMobile = $this->resolveClaimMobile($scenario, $claimStep, $baseClaimMobile);

            $claimPayload = $this->buildClaimPayload($scenario, $claimStep, $claimMobile);

            $waitBeforeSeconds = max(0, (int) data_get($claimStep, 'wait_before_seconds', 0));

            if ($waitBeforeSeconds > 0) {
                if (! $this->option('json')) {
                    $this->line(sprintf(
                        'Waiting %d second(s) before claim %s...',
                        $waitBeforeSeconds,
                        $claimKey
                    ));
                }

                sleep($waitBeforeSeconds);
                $voucher = $voucher->fresh();
            } else {
                $autoWaitSeconds = $this->resolveAutoWaitSecondsForClaim($voucher, $claimPayload);

                if ($autoWaitSeconds > 0) {
                    if (! $this->option('json')) {
                        $this->line(sprintf(
                            'Auto-waiting %d second(s) before claim %s to respect withdrawal interval...',
                            $autoWaitSeconds,
                            $claimKey
                        ));
                    }

                    sleep($autoWaitSeconds);
                    $voucher = $voucher->fresh();
                    $waitBeforeSeconds = $autoWaitSeconds;
                }
            }

            if (! $this->option('json')) {
                $this->line(sprintf(
                    'Submitting claim for voucher %s (claim: %s)...',
                    $voucher->code,
                    $claimKey
                ));
            }

            $claimPayload = $this->buildClaimPayload(
                scenario: $scenario,
                attempt: [
                    'claim' => (array) data_get($claimStep, 'claim', []),
                ],
                mobile: $claimMobile,
            );

            try {
                $claim = $submitPayCodeClaim->handle($voucher, $claimPayload);

                if (! $this->option('json')) {
                    $this->line('Polling disbursement status...');
                }

                $finalCheck = $this->pollDisbursement(
                    code: $voucher->code,
                    timeout: $timeout,
                    poll: $poll,
                    maxPolls: $maxPolls,
                    acceptPending: (bool) $this->option('accept-pending'),
                );

                $latestLedger = $this->resolveLatestVoucherClaimLedger($voucher->fresh());

                $actual = [
                    'status' => 'succeeded',
                    'message' => $this->resolveSuccessMessage($claim, $finalCheck),
                    'claim' => $claim->toArray(),
                    'disbursement_check' => $finalCheck,
                    'ledger' => $latestLedger,
                ];
            } catch (\Throwable $e) {
                $latestLedger = $this->resolveLatestVoucherClaimLedger($voucher->fresh());
                $actual = [
                    'status' => 'failed',
                    'message' => $e->getMessage(),
                    'error' => [
                        'class' => $e::class,
                        'message' => $e->getMessage(),
                    ],
                    'ledger' => $latestLedger,
                ];
            }

            $evaluation = $this->evaluateClaimExpectation($claimStep, $actual);

            $claimResults[$claimKey] = [
                'claim_mobile' => $claimMobile,
                'wait_before_seconds' => $waitBeforeSeconds,
                'claim_payload' => $claimPayload,
                'expect' => (array) data_get($claimStep, 'expect', []),
                'actual' => $actual,
                'evaluation' => $evaluation,
                'status' => $actual['status'],
                'message' => $actual['message'],
                'claim' => $actual['claim'] ?? null,
                'disbursement_check' => $actual['disbursement_check'] ?? null,
                'ledger' => $actual['ledger'] ?? null,
                'error' => $actual['error'] ?? null,
            ];

            if (! $evaluation['passed']) {
                $commandStatus = self::FAILURE;
            }

            if (! $this->option('json')) {
                $this->renderClaimEvaluation($claimKey, $evaluation, $actual);
            }
        }

        $walletTransactions = app(WalletTransactionSnapshot::class)->recentFor(
            issuer: $issuer,
            idempotencyKey: $idempotencyKey,
            voucherCode: $generated->code,
            limit: 10,
        );

        $claimSummary = $this->summarizeClaims($claimResults);

        if (! $this->option('json')) {
            $this->renderClaimsSummary($claimSummary);
        }

        return $renderer->render(
            command: $this,
            payload: [
                'scenario' => $scenarioKey,
                'label' => $scenario['label'] ?? $scenarioKey,
                'selected_attempt' => null,
                'issuer' => app(LifecycleUserSummary::class)->fromModel($issuer),
                'claim_mobile' => $baseClaimMobile,
                'claims' => $claimResults,
                'attempt_summary' => $claimSummary,
                'estimate' => $generated->cost?->toArray() ?? null,
                'generated' => $generated->toArray(),
                'wallet_transactions' => $walletTransactions,
            ],
            exitCode: $commandStatus,
        );
    }

    protected function resolveClaimMobile(array $scenario, array $claimStep, string $defaultMobile): string
    {
        $mobile = (string) (
            data_get($claimStep, 'claim.mobile')
                ?: data_get($scenario, 'claim.mobile')
                ?: $defaultMobile
        );

        if ($mobile === '') {
            throw new RuntimeException('Lifecycle claim mobile could not be resolved.');
        }

        return $mobile;
    }

    /**
     * @param  array<string, mixed>  $claimStep
     * @param  array<string, mixed>  $actual
     * @return array<string, mixed>
     */
    protected function evaluateClaimExpectation(array $claimStep, array $actual): array
    {
        $expectedStatus = (string) data_get($claimStep, 'expect.status', 'succeeded');
        $expectedClaimType = data_get($claimStep, 'expect.claim_type');

        $actualStatus = (string) ($actual['status'] ?? 'failed');
        $actualClaimType = (string) data_get($actual, 'claim.claim_type', '');

        $checks = [];

        $checks['status'] = [
            'passed' => $expectedStatus === $actualStatus,
            'expected' => $expectedStatus,
            'actual' => $actualStatus,
        ];

        if ($expectedClaimType !== null) {
            $checks['claim_type'] = [
                'passed' => (string) $expectedClaimType === $actualClaimType,
                'expected' => (string) $expectedClaimType,
                'actual' => $actualClaimType,
            ];
        }

        $passed = collect($checks)->every(fn (array $check) => (bool) ($check['passed'] ?? false));

        return [
            'passed' => $passed,
            'checks' => $checks,
            'summary' => $passed
                ? strtoupper($actualStatus).' as expected'
                : 'Claim expectation mismatch',
        ];
    }

    /**
     * @param  array<string, mixed>  $evaluation
     * @param  array<string, mixed>  $actual
     */
    protected function renderClaimEvaluation(string $claimKey, array $evaluation, array $actual): void
    {
        $summary = (string) data_get($evaluation, 'summary', 'Unknown');

        if ((bool) data_get($evaluation, 'passed', false)) {
            $this->info(sprintf('Claim [%s]: %s', $claimKey, $summary));
        } else {
            $this->error(sprintf('Claim [%s]: %s', $claimKey, $summary));
        }

        $statusCheck = (array) data_get($evaluation, 'checks.status', []);
        $this->line(sprintf(
            '  Status check: expected=%s actual=%s',
            $statusCheck['expected'] ?? 'n/a',
            $statusCheck['actual'] ?? 'n/a',
        ));

        $claimTypeCheck = (array) data_get($evaluation, 'checks.claim_type', []);
        if ($claimTypeCheck !== []) {
            $this->line(sprintf(
                '  Claim type check: expected=%s actual=%s',
                $claimTypeCheck['expected'] ?? 'n/a',
                $claimTypeCheck['actual'] ?? 'n/a',
            ));
        }

        $actualMessage = (string) ($actual['message'] ?? '');
        if ($actualMessage !== '') {
            $this->line('  Actual message: '.$actualMessage);
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $claimResults
     * @return array<string, int>
     */
    protected function summarizeClaims(array $claimResults): array
    {
        $total = count($claimResults);
        $passed = collect($claimResults)
            ->filter(fn (array $result) => (bool) data_get($result, 'evaluation.passed', false))
            ->count();

        $failed = $total - $passed;

        return [
            'passed' => $passed,
            'failed' => $failed,
            'total' => $total,
        ];
    }

    protected function renderClaimsSummary(array $summary): void
    {
        $this->newLine();
        $this->info('Claims Summary:');
        $this->line('  Passed: '.$summary['passed']);
        $this->line('  Failed: '.$summary['failed']);
        $this->line('  Total: '.$summary['total']);
    }

    protected function resolveLatestVoucherClaimLedger($voucher): ?array
    {
        $ledger = VoucherClaim::query()
            ->where('voucher_id', $voucher->getKey())
            ->latest('claim_number')
            ->latest('id')
            ->first();

        return $ledger?->toArray();
    }

    protected function resolveAutoWaitSecondsForClaim($voucher, array $claimPayload): int
    {
        if (! $this->isOpenSliceVoucher($voucher)) {
            return 0;
        }

        $minInterval = (int) config('x-change.withdrawal.open_slice_min_interval_seconds', 0);

        if ($minInterval <= 0) {
            return 0;
        }

        $currentAccountNumber = (string) data_get($claimPayload, 'bank_account.account_number', '');

        if ($currentAccountNumber === '') {
            return 0;
        }

        $lastWithdrawClaim = VoucherClaim::query()
            ->where('voucher_id', $voucher->getKey())
            ->where('claim_type', 'withdraw')
            ->latest('claim_number')
            ->latest('id')
            ->first();

        if (! $lastWithdrawClaim) {
            return 0;
        }

        $previousAccountNumber = (string) data_get($lastWithdrawClaim->meta, 'disbursement.account_number', '');

        if ($previousAccountNumber === '' || $previousAccountNumber !== $currentAccountNumber) {
            return 0;
        }

        $lastAttemptedAt = $lastWithdrawClaim->attempted_at;

        if (! $lastAttemptedAt) {
            return 0;
        }

        $elapsed = (float) $lastAttemptedAt->diffInRealSeconds(now(), true);

        if ($elapsed >= $minInterval) {
            return 0;
        }

        return (int) ceil($minInterval - $elapsed);
    }

    protected function isOpenSliceVoucher($voucher): bool
    {
        return method_exists($voucher, 'isDivisible')
            && $voucher->isDivisible()
            && method_exists($voucher, 'getSliceMode')
            && $voucher->getSliceMode() === 'open';
    }

    protected function evaluateSettlementExpectation(array $attempt, array $actual): array
    {
        $expectedStatus = (string) data_get($attempt, 'expect.status', 'ready');
        $actualStatus = (string) ($actual['status'] ?? 'failed');

        $checks = [];

        $checks['status'] = [
            'passed' => $expectedStatus === $actualStatus,
            'expected' => $expectedStatus,
            'actual' => $actualStatus,
        ];

        $expectedMissing = array_values((array) data_get($attempt, 'expect.missing', []));
        $actualMissing = array_values((array) data_get($actual, 'settlement.missing', []));

        if ($expectedMissing !== []) {
            $missingDiff = array_values(array_diff($expectedMissing, $actualMissing));

            $checks['missing'] = [
                'passed' => $missingDiff === [],
                'expected' => $expectedMissing,
                'actual' => $actualMissing,
                'missing' => $missingDiff,
            ];
        }

        $expectedSatisfied = array_values((array) data_get($attempt, 'expect.satisfied', []));
        $actualSatisfied = array_values((array) data_get($actual, 'settlement.satisfied', []));

        if ($expectedSatisfied !== []) {
            $satisfiedDiff = array_values(array_diff($expectedSatisfied, $actualSatisfied));

            $checks['satisfied'] = [
                'passed' => $satisfiedDiff === [],
                'expected' => $expectedSatisfied,
                'actual' => $actualSatisfied,
                'missing' => $satisfiedDiff,
            ];
        }

        $expectedReady = data_get($attempt, 'expect.ready');

        if ($expectedReady !== null) {
            $actualReady = (bool) data_get($actual, 'settlement.ready', false);

            $checks['ready'] = [
                'passed' => (bool) $expectedReady === $actualReady,
                'expected' => (bool) $expectedReady,
                'actual' => $actualReady,
            ];
        }

        $passed = collect($checks)->every(fn (array $check) => (bool) ($check['passed'] ?? false));

        return [
            'passed' => $passed,
            'checks' => $checks,
            'summary' => $passed
                ? strtoupper($actualStatus).' as expected'
                : 'Settlement envelope expectation mismatch',
        ];
    }

    protected function renderSettlementEvaluation(
        string $attemptKey,
        array $evaluation,
        array $actual,
    ): void {
        $summary = (string) data_get($evaluation, 'summary', 'Unknown');

        if ((bool) data_get($evaluation, 'passed', false)) {
            $this->info(sprintf('Settlement attempt [%s]: %s', $attemptKey, $summary));
        } else {
            $this->error(sprintf('Settlement attempt [%s]: %s', $attemptKey, $summary));
        }

        $statusCheck = (array) data_get($evaluation, 'checks.status', []);

        $this->line(sprintf(
            '  Status check: expected=%s actual=%s',
            $statusCheck['expected'] ?? 'n/a',
            $statusCheck['actual'] ?? 'n/a',
        ));

        $this->line(sprintf(
            '  Ready: %s',
            data_get($actual, 'settlement.ready') ? 'yes' : 'no',
        ));

        $missing = (array) data_get($actual, 'settlement.missing', []);
        $satisfied = (array) data_get($actual, 'settlement.satisfied', []);

        if ($satisfied !== []) {
            $this->line('  Satisfied: '.implode(', ', $satisfied));
        }

        if ($missing !== []) {
            $this->line('  Missing: '.implode(', ', $missing));
        }

        $actualMessage = (string) ($actual['message'] ?? '');

        if ($actualMessage !== '') {
            $this->line('  Actual message: '.$actualMessage);
        }
    }
}
