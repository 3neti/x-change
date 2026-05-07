<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Runners;

use Illuminate\Console\Command;
use LBHurtado\XChange\Actions\Redemption\SubmitPayCodeClaim;
use LBHurtado\XChange\Lifecycle\Output\LifecycleOutputContract;
use LBHurtado\XChange\Lifecycle\Runners\Support\LifecycleDisbursementPoller;
use LBHurtado\XChange\Lifecycle\Runners\Support\LifecycleUserSummary;
use LBHurtado\XChange\Lifecycle\Runners\Support\WalletTransactionSnapshot;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use RuntimeException;
use Throwable;

final class DefaultClaimScenarioRunner implements ScenarioRunnerContract
{
    public function __construct(
        private readonly LifecycleDisbursementPoller $poller,
    ) {}

    public function run(ScenarioRunContext $context): ScenarioRunResult
    {
        $output = $context->output;
        $scenarioKey = $context->scenarioKey;
        $scenario = $context->scenario;
        $issuer = $context->issuer;
        $generated = $context->generated;
        $voucher = $context->voucher;
        $attempts = $context->attempts;
        $baseClaimMobile = $context->baseClaimMobile;
        $estimate = $context->estimate;

        $idempotencyKey = $context->idempotencyKey;

        $timeout = (int) data_get($scenario, '_runtime.timeout', data_get($scenario, 'timeout', 180));
        $poll = max((int) data_get($scenario, '_runtime.poll', data_get($scenario, 'poll', 10)), 1);
        $maxPolls = data_get($scenario, '_runtime.max_polls');

        if ($maxPolls === null) {
            $maxPolls = max(1, (int) ceil($timeout / $poll));
        } else {
            $maxPolls = max(1, (int) $maxPolls);
        }

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
                $output->line(sprintf(
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
                    $output->line('Polling disbursement status...');
                }

                $finalCheck = $this->poller->poll(
                    code: $voucher->code,
                    timeout: $timeout,
                    poll: $poll,
                    maxPolls: $maxPolls,
                    acceptPending: $context->acceptPending(),
                    output: $output,
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
                    output: $output,
                    attemptKey: (string) $attemptKey,
                    evaluation: $evaluation,
                    actual: $actual,
                );
            }
        }

        $attemptSummary = $this->summarizeAttempts($attemptResults);

        if (! $context->wantsJson()) {
            $this->renderAttemptsSummary($output, $attemptSummary);
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

    private function renderAttemptEvaluation(
        LifecycleOutputContract $output,
        string $attemptKey,
        array $evaluation,
        array $actual,
    ): void {
        $summary = (string) data_get($evaluation, 'summary', 'Unknown');

        if ((bool) data_get($evaluation, 'passed', false)) {
            $output->info(sprintf('Attempt [%s]: %s', $attemptKey, $summary));
        } else {
            $output->error(sprintf('Attempt [%s]: %s', $attemptKey, $summary));
        }

        $checks = (array) data_get($evaluation, 'checks', []);

        $statusCheck = (array) ($checks['status'] ?? []);
        $messageCheck = (array) ($checks['message_contains'] ?? []);

        $output->line(sprintf(
            '  Status check: expected=%s actual=%s',
            $statusCheck['expected'] ?? 'n/a',
            $statusCheck['actual'] ?? 'n/a',
        ));

        $expectedFragments = (array) ($messageCheck['expected'] ?? []);

        if ($expectedFragments !== []) {
            $output->line(sprintf(
                '  Message check: %s',
                (bool) ($messageCheck['passed'] ?? false)
                    ? 'matched'
                    : 'missing ['.implode(', ', (array) ($messageCheck['missing'] ?? [])).']'
            ));
        }

        $actualMessage = (string) ($actual['message'] ?? '');

        if ($actualMessage !== '') {
            $output->line('  Actual message: '.$actualMessage);
        }
    }

    private function renderAttemptsSummary(LifecycleOutputContract $output, array $summary): void
    {
        $output->line('');
        $output->info('Attempts Summary:');
        $output->line('  Passed: '.$summary['passed']);
        $output->line('  Failed: '.$summary['failed']);
        $output->line('  Total: '.$summary['total']);
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

    private function resolveSuccessMessage($claim, array $finalCheck): string
    {
        $status = (string) data_get($finalCheck, 'status', '');

        if ($status !== '') {
            return "Disbursement status: {$status}";
        }

        return 'Claim submitted successfully.';
    }
}
