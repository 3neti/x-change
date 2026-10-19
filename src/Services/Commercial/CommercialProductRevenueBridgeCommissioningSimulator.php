<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

final class CommercialProductRevenueBridgeCommissioningSimulator
{
    /** @var array<string, array{amount_minor:int, currency:string, status:string}> */
    private array $transfers = [];

    /** @var array<string, array{amount_minor:int, currency:string, status:string}> */
    private array $reversals = [];

    /** @param array{transfers:list<array{amount_minor:int, currency:string, idempotency_key:string, transfer_reference:string, reversal_reference:string, reversal_idempotency_key:string}>} $plan */
    public function execute(array $plan): array
    {
        foreach ($plan['transfers'] as $transfer) {
            $this->transfers[$transfer['idempotency_key']] ??= [
                'amount_minor' => $transfer['amount_minor'],
                'currency' => $transfer['currency'],
                'status' => 'posted',
            ];
        }

        return $this->transfers;
    }

    /** @param array{transfers:list<array{amount_minor:int, currency:string, reversal_idempotency_key:string, reversal_reference:string}>} $plan */
    public function reverse(array $plan): array
    {
        foreach ($plan['transfers'] as $transfer) {
            $this->reversals[$transfer['reversal_idempotency_key']] ??= [
                'amount_minor' => $transfer['amount_minor'],
                'currency' => $transfer['currency'],
                'status' => 'reversed',
            ];
        }

        return $this->reversals;
    }
}
