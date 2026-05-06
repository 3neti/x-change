<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Lifecycle\ScenarioRunners;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use LBHurtado\XChange\Actions\Redemption\SubmitPayCodeClaim;
use LBHurtado\XChange\Console\Commands\Lifecycle\ScenarioRunners\Support\LifecycleUserSummary;
use LBHurtado\XChange\Console\Commands\Lifecycle\ScenarioRunners\Support\WalletTransactionSnapshot;
use Throwable;

final class SequentialClaimsScenarioRunner implements ScenarioRunnerContract
{
    public function __construct(
        private readonly SubmitPayCodeClaim $submitPayCodeClaim,
        private readonly WalletTransactionSnapshot $walletTransactions,
    ) {}

    public function run(ScenarioRunContext $context): ScenarioRunResult
    {
        $command = $context->command;
        $scenario = $context->scenario;
        $claims = (array) data_get($scenario, 'claims', []);

        $claimResults = [];
        $commandStatus = Command::SUCCESS;

        foreach ($claims as $claimKey => $claim) {
            $claimMobile = $this->resolveClaimMobile(
                scenario: $scenario,
                claim: $claim,
                defaultMobile: $context->baseClaimMobile,
            );

            if (! $context->wantsJson()) {
                $command->line(sprintf(
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
                    $command->line('Polling disbursement status...');
                }

                $finalCheck = $this->pollDisbursement(
                    command: $command,
                    code: $context->voucher->code,
                    timeout: (int) data_get($scenario, 'timeout', 180),
                    poll: max(1, (int) data_get($scenario, 'poll', 10)),
                    maxPolls: null,
                    acceptPending: (bool) $command->option('accept-pending'),
                );

                $actual = [
                    'status' => 'succeeded',
                    'message' => $this->resolveSuccessMessage($submittedClaim, $finalCheck),
                    'claim' => method_exists($submittedClaim, 'toArray')
                        ? $submittedClaim->toArray()
                        : $submittedClaim,
                    'disbursement_check' => $finalCheck,
                ];
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
                    command: $command,
                    attemptKey: (string) $claimKey,
                    evaluation: $evaluation,
                    actual: $actual,
                );
            }
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
        Command $command,
        string $attemptKey,
        array $evaluation,
        array $actual,
    ): void {
        $summary = (string) data_get($evaluation, 'summary', 'Unknown');

        if ((bool) data_get($evaluation, 'passed', false)) {
            $command->info(sprintf('Claim [%s]: %s', $attemptKey, $summary));
        } else {
            $command->error(sprintf('Claim [%s]: %s', $attemptKey, $summary));
        }

        $statusCheck = (array) data_get($evaluation, 'checks.status', []);
        $messageCheck = (array) data_get($evaluation, 'checks.message_contains', []);

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
                    : 'missing ['.implode(', ', (array) ($messageCheck['missing'] ?? [])).']',
            ));
        }

        $actualMessage = (string) ($actual['message'] ?? '');

        if ($actualMessage !== '') {
            $command->line('  Actual message: '.$actualMessage);
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
