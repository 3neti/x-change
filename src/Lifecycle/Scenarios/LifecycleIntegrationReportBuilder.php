<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Scenarios;

use LBHurtado\XChange\Data\Cockpit\CockpitReadModelQueryData;
use LBHurtado\XChange\Services\Cockpit\OptionalCockpitIntegrationReadModels;

final readonly class LifecycleIntegrationReportBuilder
{
    public function __construct(
        private OptionalCockpitIntegrationReadModels $integrations,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function enrich(array $payload): array
    {
        $query = new CockpitReadModelQueryData(
            code: $this->extractCode($payload),
            operatorId: $this->extractOperatorId($payload),
            include: ['journal', 'actions', 'feedback', 'campaigns'],
            correlationId: $this->extractCorrelationId($payload),
        );

        $reports = [
            'journal' => $this->integrations->journal($query)->toArray(),
            'actions' => $this->integrations->actions($query)->toArray(),
            'feedback' => $this->integrations->feedback($query)->toArray(),
            'campaigns' => $this->integrations->campaignAdoption($query)->toArray(),
        ];

        $reports['summary'] = $this->summary($reports);
        $reports['context'] = [
            'code' => $query->code,
            'operator_id' => $query->operatorId,
            'correlation_id' => $query->correlationId,
        ];

        $payload['integrations'] = $reports;

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $reports
     * @return array<string, mixed>
     */
    private function summary(array $reports): array
    {
        $statuses = collect(['journal', 'actions', 'feedback', 'campaigns'])
            ->mapWithKeys(fn (string $key): array => [$key => (string) data_get($reports, "{$key}.status", 'unavailable')]);

        $available = $statuses->filter(fn (string $status): bool => $status === 'available')->count();

        return [
            'available' => $available,
            'unavailable' => $statuses->count() - $available,
            'total' => $statuses->count(),
            'statuses' => $statuses->all(),
            'read_only' => true,
            'mutates_state' => false,
            'writes_journal' => false,
            'executes_action' => false,
            'sends_feedback' => false,
            'moves_money' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractCode(array $payload): ?string
    {
        return $this->firstString([
            data_get($payload, 'generated.code'),
            data_get($payload, 'voucher_code'),
            data_get($payload, 'disbursement_check.voucher_code'),
            data_get($payload, 'claim.voucher_code'),
            data_get($payload, 'claims.0.claim.voucher_code'),
            data_get($payload, 'claims.0.disbursement_check.voucher_code'),
            data_get($payload, 'attempts.0.claim.voucher_code'),
            data_get($payload, 'attempts.0.disbursement_check.voucher_code'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractOperatorId(array $payload): ?string
    {
        return $this->firstString([
            data_get($payload, 'issuer.id'),
            data_get($payload, 'generated.issuer.id'),
            data_get($payload, 'operator.id'),
            data_get($payload, 'operator_id'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractCorrelationId(array $payload): ?string
    {
        return $this->firstString([
            data_get($payload, 'wallet_transactions.0.idempotency_key'),
            data_get($payload, 'generated.allocations.0.meta.idempotency_key'),
            data_get($payload, 'disbursement_check.provider_reference'),
            data_get($payload, 'reconciliation.provider_reference'),
            data_get($payload, 'claims.0.disbursement_check.provider_reference'),
            data_get($payload, 'attempts.0.disbursement_check.provider_reference'),
            data_get($payload, 'generated.code'),
            data_get($payload, 'voucher_code'),
        ]);
    }

    /**
     * @param  array<int, mixed>  $values
     */
    private function firstString(array $values): ?string
    {
        foreach ($values as $value) {
            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return null;
    }
}
