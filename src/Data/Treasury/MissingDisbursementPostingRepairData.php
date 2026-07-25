<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Treasury;

final readonly class MissingDisbursementPostingRepairData
{
    /**
     * @param  list<int>  $reconciliationIds
     * @param  list<string>  $inventoryOperationReferences
     * @param  list<string>  $positionOperationReferences
     */
    public function __construct(
        public string $status,
        public string $connectionReference,
        public string $provider,
        public string $currency,
        public int $providerBalanceMinor,
        public int $inventoryBalanceMinor,
        public int $positionBalanceMinor,
        public int $deficitMinor,
        public int $candidateCount,
        public int $repairedCount,
        public int $principalAmountMinor,
        public array $reconciliationIds,
        public array $inventoryOperationReferences = [],
        public array $positionOperationReferences = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'connection_reference' => $this->connectionReference,
            'provider' => $this->provider,
            'currency' => $this->currency,
            'provider_balance_minor' => $this->providerBalanceMinor,
            'inventory_balance_minor' => $this->inventoryBalanceMinor,
            'position_balance_minor' => $this->positionBalanceMinor,
            'deficit_minor' => $this->deficitMinor,
            'candidate_count' => $this->candidateCount,
            'repaired_count' => $this->repairedCount,
            'principal_amount_minor' => $this->principalAmountMinor,
            'reconciliation_ids' => $this->reconciliationIds,
            'inventory_operation_references' => $this->inventoryOperationReferences,
            'position_operation_references' => $this->positionOperationReferences,
        ];
    }
}
