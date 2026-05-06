<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Lifecycle;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Number;
use LBHurtado\ModelChannel\Contracts\HasMobileChannel;
use LBHurtado\XChange\Actions\Redemption\SubmitPayCodeClaim;
use LBHurtado\XChange\Console\Commands\Lifecycle\ScenarioRunners\Support\LifecycleResultRenderer;
use LBHurtado\XChange\Console\Commands\Lifecycle\ScenarioRunners\Support\LifecycleScenarioBootstrapResult;
use LBHurtado\XChange\Console\Commands\Lifecycle\ScenarioRunners\Support\LifecycleScenarioEngine;
use LBHurtado\XChange\Console\Commands\Lifecycle\ScenarioRunners\Support\LifecycleScenarioRepository;
use LBHurtado\XChange\Console\Commands\Lifecycle\ScenarioRunners\Support\LifecycleScenarioRunOptions;
use LBHurtado\XChange\Console\Commands\Lifecycle\ScenarioRunners\Support\LifecycleUserSummary;
use LBHurtado\XChange\Console\Commands\Lifecycle\ScenarioRunners\Support\WalletTransactionSnapshot;
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
        LifecycleScenarioEngine $engine,
        LifecycleResultRenderer $renderer,
        LifecycleScenarioRepository $scenarioRepository,
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

        $result = $engine->run(
            command: $this,
            scenarioKey: $scenarioKey,
            options: LifecycleScenarioRunOptions::fromConsoleOptions($this->options()),
        );

        if (($result->payload['_bridge'] ?? null) === 'sequential_claims') {
            /** @var LifecycleScenarioBootstrapResult $bootstrap */
            $bootstrap = $result->payload['bootstrap'];

            return $this->runSequentialClaimsScenario(
                scenarioKey: (string) $result->payload['scenario_key'],
                scenario: (array) $result->payload['scenario'],
                issuer: $bootstrap->issuer,
                generated: $bootstrap->generated,
                voucher: $bootstrap->voucher,
                claims: (array) $result->payload['claims'],
                baseClaimMobile: $bootstrap->baseClaimMobile,
                timeout: $bootstrap->timeout,
                poll: $bootstrap->poll,
                maxPolls: $bootstrap->maxPolls,
                idempotencyKey: $bootstrap->idempotencyKey,
                submitPayCodeClaim: $submitPayCodeClaim,
                renderer: $renderer,
            );
        }

        return $renderer->render(
            command: $this,
            payload: $result->payload,
            exitCode: $result->exitCode,
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

    protected function resolveSuccessMessage($claim, array $finalCheck): string
    {
        $status = (string) data_get($finalCheck, 'status', '');

        if ($status !== '') {
            return "Disbursement status: {$status}";
        }

        return 'Claim submitted successfully.';
    }

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

    protected function renderAttemptsSummary(array $summary): void
    {
        $this->newLine();
        $this->info('Attempts Summary:');
        $this->line('  Passed: '.$summary['passed']);
        $this->line('  Failed: '.$summary['failed']);
        $this->line('  Total: '.$summary['total']);
    }

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
}
