<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\XChange\Enums\TreasuryOperatorCapability;
use LBHurtado\XChange\Models\TreasuryReconciliationRun;
use LBHurtado\XChange\Services\Treasury\TreasuryOperatorAuthority;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;

final readonly class TreasuryReconciliationRunReadModel
{
    public function __construct(
        private TreasuryOperatorAuthority $authority,
        private TreasuryProviderConnectionCatalog $connections,
    ) {}

    /** @return array<string, mixed> */
    public function build(Model $operator): array
    {
        if (! $this->authority->allows($operator, TreasuryOperatorCapability::ViewReconciliation)) {
            return $this->empty();
        }

        $runs = TreasuryReconciliationRun::query()
            ->with(['maker', 'checker'])
            ->latest()
            ->limit(100)
            ->get();

        return [
            'schema' => 'x-change.cockpit.treasury-reconciliation.v1',
            'can_view' => true,
            'can_request' => $this->authority->allows($operator, TreasuryOperatorCapability::RequestReconciliation),
            'can_approve' => $this->authority->allows($operator, TreasuryOperatorCapability::ApproveReconciliation),
            'can_execute' => $this->authority->allows($operator, TreasuryOperatorCapability::ExecuteReconciliation),
            'connections' => array_map(static fn ($connection): array => [
                'reference' => $connection->reference,
                'provider' => $connection->provider,
                'currency' => $connection->currency,
            ], $this->connections->active()),
            'runs' => $runs->map(fn (TreasuryReconciliationRun $run): array => [
                'reference' => $run->reference,
                'status' => $run->status->value,
                'connection_reference' => $run->connection_reference,
                'provider' => $run->provider,
                'currency' => $run->currency,
                'purpose' => $run->purpose,
                'maker' => (string) ($run->maker?->getAttribute('name') ?: 'Named operator'),
                'checker' => $run->checker instanceof Model
                    ? (string) ($run->checker->getAttribute('name') ?: 'Named operator')
                    : null,
                'provider_balance' => $this->money($run->provider_balance_minor, $run->currency),
                'internal_balance' => $this->money($run->position_balance_minor, $run->currency),
                'difference' => $this->money($run->difference_minor, $run->currency),
                'evidence_reference' => $run->evidence_reference,
                'reason' => $run->reason,
                'observed_at' => $run->observed_at?->toIso8601String(),
                'actions' => [
                    'approve' => route('x-change.cockpit.treasury.reconciliation.approvals.store', $run),
                    'execute' => route('x-change.cockpit.treasury.reconciliation.executions.store', $run),
                ],
            ])->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function empty(): array
    {
        return [
            'schema' => 'x-change.cockpit.treasury-reconciliation.v1',
            'can_view' => false,
            'can_request' => false,
            'can_approve' => false,
            'can_execute' => false,
            'connections' => [],
            'runs' => [],
        ];
    }

    private function money(?int $amountMinor, string $currency): ?string
    {
        if ($amountMinor === null) {
            return null;
        }

        $prefix = mb_strtoupper($currency) === 'PHP' ? '₱' : mb_strtoupper($currency).' ';

        return $prefix.number_format($amountMinor / 100, 2);
    }
}
