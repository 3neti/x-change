<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Lifecycle\ScenarioRunners;

use Illuminate\Console\Command;
use LBHurtado\XChange\Actions\Redemption\SubmitPayCodeClaim;
use LBHurtado\XChange\Console\Commands\Lifecycle\ScenarioRunners\Support\LifecycleDisbursementPoller;
use LBHurtado\XChange\Console\Commands\Lifecycle\ScenarioRunners\Support\LifecycleOutputContract;
use LBHurtado\XChange\Console\Commands\Lifecycle\ScenarioRunners\Support\LifecycleUserSummary;
use LBHurtado\XChange\Console\Commands\Lifecycle\ScenarioRunners\Support\WalletTransactionSnapshot;
use LBHurtado\XChange\Models\VoucherClaim;
use Throwable;

final class SequentialClaimsScenarioRunner implements ScenarioRunnerContract
{
    public function __construct(
        private readonly SubmitPayCodeClaim $submitPayCodeClaim,
        private readonly WalletTransactionSnapshot $walletTransactions,
        private readonly LifecycleDisbursementPoller $poller,
    ) {}

    public function run(ScenarioRunContext $context): ScenarioRunResult
    {
        $output = $context->output;
        $scenario = $context->scenario;
        $claims = (array) data_get($scenario, 'claims', []);

        $claimResults = [];
        $commandStatus = Command::SUCCESS;
        $claimIndex = 0;

        foreach ($claims as $claimKey => $claim) {
            $waitBeforeSeconds = $this->resolveWaitBeforeSeconds(
                scenario: $scenario,
                claim: $claim,
                claimIndex: $claimIndex,
            );

            if ($waitBeforeSeconds > 0) {
                if (! $context->wantsJson()) {
                    $output->line(sprintf(
                        'Waiting %d seconds before claim [%s]...',
                        $waitBeforeSeconds,
                        $claimKey,
                    ));
                }

                sleep($waitBeforeSeconds);
            }

            $claimMobile = $this->resolveClaimMobile(
                scenario: $scenario,
                claim: $claim,
                defaultMobile: $context->baseClaimMobile,
            );

            if (! $context->wantsJson()) {
                $output->line(sprintf(
                    'Claim [%s] using mobile %s...',
                    $claimKey,
                    $claimMobile,
                ));
            }

            $claimPayload = $this->buildClaimPayload(
                scenario: $scenario,
                claim: $claim,
                mobile: $claimMobile,
            );

            try {
                $submittedClaim = $this->submitPayCodeClaim->handle(
                    $context->voucher,
                    $claimPayload,
                );

                if (! $context->wantsJson()) {
                    $output->line('Polling disbursement status...');
                }

                $finalCheck = $this->poller->poll(
                    code: $context->voucher->code,
                    timeout: (int) data_get($scenario, 'timeout', 180),
                    poll: max(1, (int) data_get($scenario, 'poll', 10)),
                    maxPolls: null,
                    acceptPending: $context->acceptPending(),
                    output: $output,
                );

                $actual = [
                    'status' => 'succeeded',
                    'message' => $this->resolveSuccessMessage($submittedClaim, $finalCheck),
                    'claim' => method_exists($submittedClaim, 'toArray')
                        ? $submittedClaim->toArray()
                        : $submittedClaim,
                    'disbursement_check' => $finalCheck,
                ];

                $this->persistWaitMetadata(
                    voucherId: (int) $context->voucher->getKey(),
                    claimKey: (string) $claimKey,
                    waitBeforeSeconds: $waitBeforeSeconds,
                );
            } catch (Throwable $exception) {
                $actual = $this->normalizeException($exception);
            }

            $evaluation = $this->evaluateExpectation(
                attempt: $claim,
                actual: $actual,
            );

            if (! (bool) data_get($evaluation, 'passed', false)) {
                $commandStatus = Command::FAILURE;
            }

            $claimResults[$claimKey] = [
                'claim_mobile' => $claimMobile,
                'wait_before_seconds' => $waitBeforeSeconds,
                'claim_payload' => $claimPayload,
                'expect' => (array) data_get($claim, 'expect', []),
                'actual' => $actual,
                'evaluation' => $evaluation,
                'status' => $actual['status'] ?? null,
                'message' => $actual['message'] ?? null,
                'claim' => $actual['claim'] ?? null,
                'disbursement_check' => $actual['disbursement_check'] ?? null,
                'error' => $actual['error'] ?? null,
            ];

            if (! $context->wantsJson()) {
                $this->renderAttemptEvaluation(
                    output: $output,
                    attemptKey: (string) $claimKey,
                    evaluation: $evaluation,
                    actual: $actual,
                );
            }

            $claimIndex++;
        }

        $claimSummary = $this->summarizeAttempts($claimResults);

        $walletTransactions = $this->walletTransactions->recentFor(
            issuer: $context->issuer,
            idempotencyKey: $context->idempotencyKey,
            voucherCode: $context->voucher->code,
            limit: 10,
        );

        return new ScenarioRunResult(
            exitCode: $commandStatus,
            payload: [
                'scenario' => $context->scenarioKey,
                'label' => $context->label(),
                'mode' => $context->mode(),
                'selected_attempt' => null,
                'issuer' => app(LifecycleUserSummary::class)->fromModel($context->issuer),
                'claim_mobile' => $context->baseClaimMobile,
                'claims' => $claimResults,
                'attempt_summary' => $claimSummary,
                'estimate' => $context->estimate,
                'generated' => $context->generated->toArray(),
                'wallet_transactions' => $walletTransactions,
            ],
        );
    }

    private function resolveClaimMobile(array $scenario, array $claim, string $defaultMobile): string
    {
        return (string) (
        data_get($claim, 'mobile')
            ?: data_get($claim, 'claim.mobile')
            ?: data_get($scenario, 'claim.mobile')
                ?: data_get($scenario, 'mobile')
                    ?: $defaultMobile
        );
    }

    private function buildClaimPayload(array $scenario, array $claim, string $mobile): array
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
            (array) data_get($claim, 'claim', []),
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

    private function evaluateExpectation(array $attempt, array $actual): array
    {
        $expectedStatus = (string) data_get($attempt, 'expect.status', 'succeeded');
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
            fn ($value): bool => is_string($value) && trim($value) !== '',
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
            fn (array $check): bool => (bool) ($check['passed'] ?? false),
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
            $output->info(sprintf('Claim [%s]: %s', $attemptKey, $summary));
        } else {
            $output->error(sprintf('Claim [%s]: %s', $attemptKey, $summary));
        }

        $statusCheck = (array) data_get($evaluation, 'checks.status', []);
        $messageCheck = (array) data_get($evaluation, 'checks.message_contains', []);

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
                    : 'missing ['.implode(', ', (array) ($messageCheck['missing'] ?? [])).']',
            ));
        }

        $actualMessage = (string) ($actual['message'] ?? '');

        if ($actualMessage !== '') {
            $output->line('  Actual message: '.$actualMessage);
        }
    }

    private function resolveSuccessMessage(mixed $claim, array $finalCheck): string
    {
        $status = (string) data_get($finalCheck, 'status', data_get($finalCheck, 'current_status', ''));

        if ($status !== '') {
            return "Disbursement status: {$status}";
        }

        return 'Claim submitted successfully.';
    }

    private function resolveWaitBeforeSeconds(array $scenario, array $claim, int $claimIndex): int
    {
        if ($claimIndex === 0) {
            return 0;
        }

        $runtimeOverride = data_get($scenario, '_runtime.sequential_wait_between_claims_seconds');

        if (app()->environment('testing') && $runtimeOverride !== null) {
            return max(0, (int) $runtimeOverride);
        }

        $explicit = data_get($claim, 'wait_before_seconds')
            ?? data_get($claim, 'wait.before_seconds')
            ?? data_get($claim, 'delay_before_seconds')
            ?? data_get($claim, 'pause_before_seconds');

        if ($explicit !== null) {
            return max(0, (int) $explicit);
        }

        $default = data_get($scenario, 'sequential.wait_between_claims_seconds')
            ?? data_get($scenario, 'claims_wait_between_seconds')
            ?? data_get($scenario, 'wait_between_claims_seconds');

        return max(0, (int) ($default ?? 0));
    }

    private function persistWaitMetadata(
        int $voucherId,
        string $claimKey,
        int $waitBeforeSeconds,
    ): void {
        $claim = VoucherClaim::query()
            ->where('voucher_id', $voucherId)
            ->latest('id')
            ->first();

        if (! $claim) {
            return;
        }

        $meta = is_array($claim->meta) ? $claim->meta : [];

        $claim->forceFill([
            'meta' => [
                ...$meta,
                'lifecycle_claim_key' => $claimKey,
                'wait_before_seconds' => $waitBeforeSeconds,
            ],
        ])->save();
    }
}
