<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Runners;

use Illuminate\Console\Command;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Data\ExecutionResultData;
use LBHurtado\Voucher\Services\ExecutionEngine;
use LBHurtado\XChange\Contracts\ExecutionResultHandoffPipelineContract;
use LBHurtado\XChange\Data\Execution\ExecutionResultHandoffSummaryData;
use Propaganistas\LaravelPhone\PhoneNumber;

final class ExecutionEngineContractScenarioRunner implements ScenarioRunnerContract
{
    public function __construct(
        private readonly ExecutionEngine $engine,
        private readonly ExecutionResultHandoffPipelineContract $handoffs,
    ) {}

    public function run(ScenarioRunContext $context): ScenarioRunResult
    {
        $executions = [];

        foreach ($this->operations($context) as $operation) {
            $executionContext = ExecutionContextData::fromRedemption(
                voucher: $context->voucher,
                contact: $this->contactFor($context),
                voucherCode: (string) $context->voucher->code,
                meta: $operation,
                correlation: [
                    'scenario' => $context->scenarioKey,
                    'idempotency_key' => $context->idempotencyKey,
                ],
            );

            $result = $this->engine->execute($executionContext);
            $handoffs = $this->handoffs->process($result, $executionContext);

            $executions[] = $this->formatResult($result, $operation, $handoffs);
        }

        $successful = collect($executions)
            ->every(fn (array $execution): bool => (bool) $execution['successful']);

        return new ScenarioRunResult(
            exitCode: $successful ? Command::SUCCESS : Command::FAILURE,
            payload: [
                'success' => $successful,
                'scenario' => $context->scenarioKey,
                'label' => $context->label(),
                'mode' => 'execution_engine_contract_demo',
                'voucher_code' => (string) $context->voucher->code,
                'execution_instruction' => data_get($context->voucher->instructions?->toArray(), 'execution', []),
                'executions' => $executions,
                'execution' => $executions[0] ?? null,
                'contract_boundary' => [
                    'engine_owner' => 'voucher',
                    'gateway_owner' => 'x-change',
                    'provider_side_effects' => $this->performsLiveProviderSideEffects($executions)
                        ? 'performed'
                        : 'not-performed',
                    'wallet_side_effects' => $this->performsLiveProviderSideEffects($executions)
                        ? 'performed'
                        : 'not-performed',
                ],
            ],
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function operations(ScenarioRunContext $context): array
    {
        $sequence = data_get($context->scenario, 'execution_runtime.sequence');

        if (is_array($sequence) && $sequence !== []) {
            return array_values(array_filter($sequence, 'is_array'));
        }

        $operation = data_get($context->scenario, 'execution_runtime.operation');

        if (is_array($operation)) {
            return [$operation];
        }

        return [['operation' => 'execute']];
    }

    private function contactFor(ScenarioRunContext $context): Contact
    {
        return Contact::fromPhoneNumber(new PhoneNumber($context->baseClaimMobile, 'PH'));
    }

    /**
     * @param  array<string, mixed>  $operation
     * @return array<string, mixed>
     */
    private function formatResult(
        ExecutionResultData $result,
        array $operation,
        ExecutionResultHandoffSummaryData $handoffs,
    ): array {
        return [
            'operation' => (string) ($operation['operation'] ?? 'execute'),
            'execution_id' => $result->execution_id,
            'successful' => $result->successful,
            'status' => $result->status,
            'driver' => $result->driver,
            'events' => $result->events,
            'failure' => $result->failure,
            'provider_references' => $result->providerReferences,
            'reconciliation' => $result->reconciliation,
            'children' => $result->children,
            'metadata' => $result->metadata,
            'handoffs' => $handoffs->toReportArray(),
            'projection_profile' => $this->projectionProfile($handoffs),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function projectionProfile(ExecutionResultHandoffSummaryData $handoffs): array
    {
        $report = $handoffs->toReportArray();
        $summaryJournal = data_get($report, 'handoff_summary_journal');
        $summaryStatus = is_array($summaryJournal)
            ? (string) ($summaryJournal['status'] ?? 'not_wired')
            : 'not_wired';
        $summaryRecorded = $summaryStatus === 'recorded';
        $targets = $this->stringMap(data_get($report, 'profile.targets'));
        $projectedTargets = $this->stringList(data_get($report, 'profile.active_targets'));
        $sideEffectTargets = $this->stringList(data_get($report, 'profile.performed_side_effect_targets'));

        return [
            'schema' => 'x-change.execution-projection-profile.v1',
            'status' => $summaryRecorded
                ? 'durable_summary_evidence_available'
                : 'runtime_handoff_profile_only',
            'execution_id' => $handoffs->execution_id,
            'voucher_code' => $handoffs->voucher_code,
            'correlation_id' => $handoffs->correlation_id,
            'targets' => $targets,
            'projected_targets' => $projectedTargets,
            'performed_side_effect_targets' => $sideEffectTargets,
            'failed_targets' => $this->stringList(data_get($report, 'profile.failed_targets')),
            'cockpit_projection' => [
                'source' => $summaryRecorded
                    ? 'x-journal.execution.handoff.summary.recorded'
                    : 'execution.handoffs.profile',
                'summary_event_type' => $summaryRecorded
                    ? 'execution.handoff.summary.recorded'
                    : null,
                'summary_reference_number' => is_array($summaryJournal)
                    ? $this->nullableString(data_get($summaryJournal, 'metadata.reference_number'))
                    : null,
                'read_only' => true,
                'mutates_execution' => false,
                'writes_journal' => false,
                'sends_feedback' => false,
                'executes_action' => false,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function stringMap(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->mapWithKeys(fn (mixed $item, mixed $key): array => [
                (string) $key => is_scalar($item) ? (string) $item : '',
            ])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->filter(fn (mixed $item): bool => is_scalar($item) && trim((string) $item) !== '')
            ->map(fn (mixed $item): string => (string) $item)
            ->values()
            ->all();
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    /**
     * @param  array<int, array<string, mixed>>  $executions
     */
    private function performsLiveProviderSideEffects(array $executions): bool
    {
        return collect($executions)
            ->contains(fn (array $execution): bool => ($execution['driver'] ?? null) === 'x_change_live_cash');
    }
}
