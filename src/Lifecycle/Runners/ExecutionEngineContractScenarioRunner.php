<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Runners;

use Illuminate\Console\Command;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Data\ExecutionResultData;
use LBHurtado\Voucher\Services\ExecutionEngine;
use Propaganistas\LaravelPhone\PhoneNumber;

final class ExecutionEngineContractScenarioRunner implements ScenarioRunnerContract
{
    public function __construct(
        private readonly ExecutionEngine $engine,
    ) {}

    public function run(ScenarioRunContext $context): ScenarioRunResult
    {
        $executions = [];

        foreach ($this->operations($context) as $operation) {
            $result = $this->engine->execute(
                ExecutionContextData::fromRedemption(
                    voucher: $context->voucher,
                    contact: $this->contactFor($context),
                    voucherCode: (string) $context->voucher->code,
                    meta: $operation,
                    correlation: [
                        'scenario' => $context->scenarioKey,
                        'idempotency_key' => $context->idempotencyKey,
                    ],
                ),
            );

            $executions[] = $this->formatResult($result, $operation);
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
    private function formatResult(ExecutionResultData $result, array $operation): array
    {
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
        ];
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
