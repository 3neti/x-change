<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Runners;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Number;
use LBHurtado\XChange\Contracts\SettlementEnvelopeReadinessContract;
use LBHurtado\XChange\Data\Settlement\SettlementEnvelopeReadinessData;
use LBHurtado\XChange\Lifecycle\Output\LifecycleOutputContract;
use LBHurtado\XChange\Lifecycle\Runners\Support\LifecycleUserSummary;
use LBHurtado\XChange\Lifecycle\Runners\Support\SettlementEnvelopeContextBuilder;
use LBHurtado\XChange\Lifecycle\Runners\Support\SettlementPhaseSummary;

final class SettlementEnvelopeEvaluationScenarioRunner implements ScenarioRunnerContract
{
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
        $readiness = $context->readiness ?? app(SettlementEnvelopeReadinessContract::class);

        $attemptResults = [];
        $exitCode = Command::SUCCESS;

        $contexts = app(SettlementEnvelopeContextBuilder::class);
        $summaries = app(SettlementPhaseSummary::class);

        foreach ($attempts as $attemptKey => $attempt) {
            $gate = $contexts->resolveGate($scenario, $attempt);

            $driver = (string) (
            data_get($attempt, 'settlement.driver')
                ?: data_get($scenario, 'settlement.driver')
                ?: data_get($scenario, 'metadata.settlement_driver')
                    ?: config('x-change.settlement.default_driver', 'philhealth-bst')
            );

            if (! $context->wantsJson()) {
                $output->line(sprintf(
                    'Evaluating settlement envelope for voucher %s (attempt: %s)...',
                    $voucher->code,
                    $attemptKey
                ));
            }

            $readinessContext = $contexts->fromScenarioAttempt($scenario, $attempt);

            try {
                $result = $readiness->evaluate(
                    voucher: $voucher,
                    gate: $gate,
                    context: $readinessContext,
                );

                $actual = [
                    'status' => $result->ready ? 'ready' : 'blocked',
                    'message' => $result->ready
                        ? 'Settlement envelope is ready.'
                        : 'Settlement envelope is not ready.',
                    'settlement' => $this->formatSettlementReadiness($result),
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

            $evaluation = $this->evaluateSettlementExpectation($attempt, $actual);

            $attemptResults[$attemptKey] = [
                'settlement_context' => $readinessContext,
                'expect' => (array) data_get($attempt, 'expect', []),
                'actual' => $actual,
                'evaluation' => $evaluation,
                'status' => $actual['status'],
                'message' => $actual['message'],
                'settlement' => $actual['settlement'] ?? null,
                'error' => $actual['error'] ?? null,
            ];

            if (! $evaluation['passed']) {
                $exitCode = Command::FAILURE;
            }

            if (! $context->wantsJson()) {
                $this->renderSettlementEvaluation($output, (string) $attemptKey, $evaluation, $actual);
            }
        }

        $walletTransactions = $this->recentWalletTransactions(
            issuer: $issuer,
            idempotencyKey: $idempotencyKey,
            voucherCode: $generated->code,
            limit: 10,
        );

        $attemptSummary = $summaries->fromAttempts($attemptResults);

        if (! $context->wantsJson()) {
            $this->renderAttemptsSummary($output, $attemptSummary);
        }

        return new ScenarioRunResult(
            exitCode: $exitCode,
            payload: [
                'scenario' => $scenarioKey,
                'label' => $scenario['label'] ?? $scenarioKey,
                'mode' => 'settlement_envelope_evaluation',
                'selected_attempt' => $context->selectedAttempt(),
                'issuer' => app(LifecycleUserSummary::class)->fromModel($issuer),
                'claim_mobile' => $baseClaimMobile,
                'attempts' => $attemptResults,
                'attempt_summary' => $attemptSummary,
                'estimate' => $estimate,
                'generated' => $generated->toArray(),
                'wallet_transactions' => $walletTransactions,
            ],
        );
    }

    private function formatSettlementReadiness(SettlementEnvelopeReadinessData $readiness): array
    {
        return [
            'required' => $readiness->required,
            'exists' => $readiness->exists,
            'ready' => $readiness->ready,
            'driver' => $readiness->driver,
            'gate' => $readiness->gate,
            'satisfied' => $readiness->satisfied,
            'missing' => $readiness->missing,
            'failed' => $readiness->failed,
            'warnings' => $readiness->warnings,
            'checklist' => $readiness->checklist,
            'payload' => $readiness->payload,
            'documents' => $readiness->documents,
            'meta' => $readiness->meta,
        ];
    }

    private function evaluateSettlementExpectation(array $attempt, array $actual): array
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

    private function renderSettlementEvaluation(
        LifecycleOutputContract $output,
        string $attemptKey,
        array $evaluation,
        array $actual,
    ): void {
        $summary = (string) data_get($evaluation, 'summary', 'Unknown');

        if ((bool) data_get($evaluation, 'passed', false)) {
            $output->info(sprintf('Settlement attempt [%s]: %s', $attemptKey, $summary));
        } else {
            $output->error(sprintf('Settlement attempt [%s]: %s', $attemptKey, $summary));
        }

        $statusCheck = (array) data_get($evaluation, 'checks.status', []);

        $output->line(sprintf(
            '  Status check: expected=%s actual=%s',
            $statusCheck['expected'] ?? 'n/a',
            $statusCheck['actual'] ?? 'n/a',
        ));

        $output->line(sprintf(
            '  Ready: %s',
            data_get($actual, 'settlement.ready') ? 'yes' : 'no',
        ));

        $missing = (array) data_get($actual, 'settlement.missing', []);
        $satisfied = (array) data_get($actual, 'settlement.satisfied', []);

        if ($satisfied !== []) {
            $output->line('  Satisfied: '.implode(', ', $satisfied));
        }

        if ($missing !== []) {
            $output->line('  Missing: '.implode(', ', $missing));
        }

        $actualMessage = (string) ($actual['message'] ?? '');

        if ($actualMessage !== '') {
            $output->line('  Actual message: '.$actualMessage);
        }
    }

    private function summarizeAttempts(array $attemptResults): array
    {
        $total = count($attemptResults);
        $passed = collect($attemptResults)
            ->filter(fn (array $result) => (bool) data_get($result, 'evaluation.passed', false))
            ->count();

        return [
            'passed' => $passed,
            'failed' => $total - $passed,
            'total' => $total,
        ];
    }

    private function renderAttemptsSummary(LifecycleOutputContract $output, array $summary): void
    {
        $output->line('');
        $output->info('Attempts Summary:');
        $output->line('  Passed: '.$summary['passed']);
        $output->line('  Failed: '.$summary['failed']);
        $output->line('  Total: '.$summary['total']);
    }

    private function recentWalletTransactions(
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

        return $wallet->transactions()
            ->latest('id')
            ->limit(max($limit, 1) * 5)
            ->get()
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
                    'confirmed' => isset($transaction->confirmed) ? (bool) $transaction->confirmed : null,
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

    private function normalizeTransactionMeta(mixed $meta): array
    {
        if (is_array($meta)) {
            return $meta;
        }

        if (is_string($meta) && $meta !== '') {
            $decoded = json_decode($meta, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function resolveTransactionAmountMinor(mixed $transaction): int
    {
        $amount = $transaction->amount ?? null;

        if (is_numeric($amount)) {
            return (int) $amount;
        }

        if (isset($transaction->amount_minor) && is_numeric($transaction->amount_minor)) {
            return (int) $transaction->amount_minor;
        }

        return 0;
    }
}
