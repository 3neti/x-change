<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Lifecycle\ScenarioRunners;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use LBHurtado\XChange\Actions\Redemption\SubmitPayCodeClaim;
use LBHurtado\XChange\Console\Commands\Lifecycle\ScenarioRunners\Support\LifecycleUserSummary;
use LBHurtado\XChange\Console\Commands\Lifecycle\ScenarioRunners\Support\WalletTransactionSnapshot;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use RuntimeException;
use Throwable;

final class DefaultClaimScenarioRunner implements ScenarioRunnerContract
{
    public function run(ScenarioRunContext $context): ScenarioRunResult
    {
        $command = $context->command;
        $scenarioKey = $context->scenarioKey;
        $scenario = $context->scenario;
        $issuer = $context->issuer;
        $generated = $context->generated;
        $voucher = $context->voucher;
        $attempts = $context->attempts;
        $baseClaimMobile = $context->baseClaimMobile;
        $estimate = $context->estimate;

        $idempotencyKey = $context->idempotencyKey;

        $timeout = (int) $command->option('timeout');
        $poll = max((int) $command->option('poll'), 1);
        $maxPolls = $timeout <= 0 ? 1 : null;
        $timeout = max($timeout, $poll);

        $attemptResults = [];
        $exitCode = Command::SUCCESS;

        foreach ($attempts as $attemptKey => $attempt) {
            $attemptMobile = $this->resolveAttemptMobile(
                scenario: $scenario,
                attempt: $attempt,
                defaultMobile: $baseClaimMobile,
            );

            if (! $context->wantsJson()) {
                $command->line(sprintf(
                    'Attempt [%s] using mobile %s...',
                    $attemptKey,
                    $attemptMobile,
                ));
            }

            $claimPayload = $this->buildClaimPayload(
                scenario: $scenario,
                attempt: $attempt,
                mobile: $attemptMobile,
            );

            try {
                $claim = app(SubmitPayCodeClaim::class)->handle($voucher, $claimPayload);

                if (! $context->wantsJson()) {
                    $command->line('Polling disbursement status...');
                }

                $finalCheck = $this->pollDisbursement(
                    command: $command,
                    code: $voucher->code,
                    timeout: $timeout,
                    poll: $poll,
                    maxPolls: $maxPolls,
                    acceptPending: (bool) $command->option('accept-pending'),
                );

                $actual = [
                    'status' => 'succeeded',
                    'message' => $this->resolveSuccessMessage($claim, $finalCheck),
                    'claim' => $claim->toArray(),
                    'disbursement_check' => $finalCheck,
                ];
            } catch (Throwable $exception) {
                $actual = $this->normalizeException($exception);
            }

            $evaluation = $this->evaluateExpectation(
                attempt: $attempt,
                actual: $actual,
            );

            if (! $evaluation['passed']) {
                $exitCode = Command::FAILURE;
            }

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

            if (! $context->wantsJson()) {
                $this->renderAttemptEvaluation(
                    command: $command,
                    attemptKey: (string) $attemptKey,
                    evaluation: $evaluation,
                    actual: $actual,
                );
            }
        }

        $attemptSummary = $this->summarizeAttempts($attemptResults);

        if (! $context->wantsJson()) {
            $this->renderAttemptsSummary($command, $attemptSummary);
        }

        $reconciliation = DisbursementReconciliation::query()
            ->where('voucher_code', $voucher->code)
            ->latest('id')
            ->first();

        $walletTransactions = app(WalletTransactionSnapshot::class)->recentFor(
            issuer: $issuer,
            idempotencyKey: $idempotencyKey,
            voucherCode: $voucher->code,
            limit: 10,
        );

        return new ScenarioRunResult(
            exitCode: $exitCode,
            payload: [
                'scenario' => $scenarioKey,
                'label' => $context->label(),
                'mode' => $scenario['mode'] ?? 'default',
                'selected_attempt' => $context->selectedAttempt(),
                'issuer' => app(LifecycleUserSummary::class)->fromModel($issuer),
                'claim_mobile' => $baseClaimMobile,
                'attempts' => $attemptResults,
                'attempt_summary' => $attemptSummary,
                'estimate' => $estimate,
                'generated' => $generated->toArray(),
                'reconciliation' => $reconciliation?->toArray(),
                'wallet_transactions' => $walletTransactions,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildClaimPayload(array $scenario, array $attempt, string $mobile): array
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

    private function submitClaim(string $voucherCode, array $payload): array
    {
        $result = app(SubmitPayCodeClaim::class)->run($voucherCode, $payload);

        if (is_array($result)) {
            return $result;
        }

        if (is_object($result) && method_exists($result, 'toArray')) {
            return $result->toArray();
        }

        return [
            'status' => 'succeeded',
            'message' => 'Claim submitted.',
            'result' => $result,
        ];
    }

    private function normalizeException(Throwable $exception): array
    {
        return [
            'status' => 'failed',
            'message' => $exception->getMessage(),
            'error' => [
                'class' => $exception::class,
                'message' => $exception->getMessage(),
            ],
        ];
    }

    private function expectedAttemptStatus(array $attempt, string $default = 'succeeded'): string
    {
        return (string) data_get($attempt, 'expect.status', $default);
    }

    /**
     * @param  array<string, mixed>  $attempt
     * @param  array<string, mixed>  $actual
     * @return array<string, mixed>
     */
    private function evaluateExpectation(array $attempt, array $actual): array
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

        $passed = collect($checks)->every(
            fn (array $check) => (bool) ($check['passed'] ?? false)
        );

        return [
            'passed' => $passed,
            'checks' => $checks,
            'summary' => $this->summarizeEvaluation(
                passed: $passed,
                statusPassed: $statusPassed,
                messagePassed: $messagePassed,
                expectedStatus: $expectedStatus,
                actualStatus: $actualStatus,
            ),
        ];
    }

    private function summarizeEvaluation(
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

    private function summarizeAttempts(array $attemptResults): array
    {
        $total = count($attemptResults);

        $passed = collect($attemptResults)
            ->filter(fn (array $result): bool => (bool) data_get($result, 'evaluation.passed', false))
            ->count();

        return [
            'passed' => $passed,
            'failed' => $total - $passed,
            'total' => $total,
        ];
    }

    /**
     * @param  array<string, mixed>  $evaluation
     * @param  array<string, mixed>  $actual
     */
    private function renderAttemptEvaluation(
        Command $command,
        string $attemptKey,
        array $evaluation,
        array $actual,
    ): void {
        $summary = (string) data_get($evaluation, 'summary', 'Unknown');

        if ((bool) data_get($evaluation, 'passed', false)) {
            $command->info(sprintf('Attempt [%s]: %s', $attemptKey, $summary));
        } else {
            $command->error(sprintf('Attempt [%s]: %s', $attemptKey, $summary));
        }

        $checks = (array) data_get($evaluation, 'checks', []);

        $statusCheck = (array) ($checks['status'] ?? []);
        $messageCheck = (array) ($checks['message_contains'] ?? []);

        $command->line(sprintf(
            '  Status check: expected=%s actual=%s',
            $statusCheck['expected'] ?? 'n/a',
            $statusCheck['actual'] ?? 'n/a',
        ));

        $expectedFragments = (array) ($messageCheck['expected'] ?? []);

        if ($expectedFragments !== []) {
            $command->line(sprintf(
                '  Message check: %s',
                (bool) ($messageCheck['passed'] ?? false)
                    ? 'matched'
                    : 'missing ['.implode(', ', (array) ($messageCheck['missing'] ?? [])).']'
            ));
        }

        $actualMessage = (string) ($actual['message'] ?? '');

        if ($actualMessage !== '') {
            $command->line('  Actual message: '.$actualMessage);
        }
    }

    private function renderAttemptsSummary(Command $command, array $summary): void
    {
        $command->newLine();
        $command->info('Attempts Summary:');
        $command->line('  Passed: '.$summary['passed']);
        $command->line('  Failed: '.$summary['failed']);
        $command->line('  Total: '.$summary['total']);
    }

    private function resolveAttemptMobile(array $scenario, array $attempt, string $defaultMobile): string
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

    /**
     * @param  mixed  $claim
     * @param  array<string, mixed>  $finalCheck
     */
    private function resolveSuccessMessage($claim, array $finalCheck): string
    {
        $status = (string) data_get($finalCheck, 'status', '');

        if ($status !== '') {
            return "Disbursement status: {$status}";
        }

        return 'Claim submitted successfully.';
    }

    /**
     * @return array<string, mixed>
     */
    private function pollDisbursement(
        Command $command,
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

                if (! $command->option('json')) {
                    $elapsed = time() - $start;
                    $maxPollsLabel = $maxPolls !== null ? (string) $maxPolls : '∞';

                    $command->line(sprintf(
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
                    if (! $command->option('json')) {
                        $command->info('Trusted pending transaction accepted as good enough.');
                    }

                    return $decoded;
                }
            } elseif (! $command->option('json')) {
                $elapsed = time() - $start;
                $command->warn("[poll {$elapsed}s] unable to decode disbursement status response");
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
}
