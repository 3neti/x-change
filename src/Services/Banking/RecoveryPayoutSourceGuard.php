<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Banking;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Models\BankAccountAssignment;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;
use RuntimeException;

final class RecoveryPayoutSourceGuard
{
    public function __construct(
        private readonly VoucherBankSourceSnapshot $sources,
        private readonly TreasuryProviderConnectionCatalog $connections,
    ) {}

    public function assertMatches(Voucher $voucher, DisbursementReconciliation $reconciliation): void
    {
        $source = $this->sources->forVoucher($voucher);
        $reservation = data_get($voucher->metadata, 'treasury.pay_code_reservation.connection_reference');

        if ($source === null && is_string($reservation) && BankAccountAssignment::query()
            ->where('treasury_connection_reference', $reservation)->exists()) {
            throw new RuntimeException('The Pay Code has no frozen source for its assigned bank account.');
        }

        if ($source === null) {
            return;
        }

        if (! is_array($reconciliation->bank_source_snapshot) || $reconciliation->bank_source_snapshot !== $source) {
            throw new RuntimeException('The recovery payout source does not match the Pay Code source.');
        }

        if (! filled($source['provider'] ?? null) || ! filled($source['account_number'] ?? null)
            || ! filled($source['customer_id'] ?? null)) {
            throw new RuntimeException('The frozen payout source is incomplete.');
        }

        if (is_string($reservation)) {
            if (($source['treasury_connection_reference'] ?? null) !== $reservation) {
                throw new RuntimeException('The Pay Code Treasury reservation does not match its frozen source.');
            }
            $connections = $this->connections->active([$reservation]);
            $connection = count($connections) === 1 ? $connections[0] : null;
            if ($connection === null || $connection->provider !== ($source['provider'] ?? null)
                || $connection->inventoryReference !== ($source['treasury_inventory_reference'] ?? null)
                || $connection->settlementResourceReference !== ($source['treasury_resource_reference'] ?? null)) {
                throw new RuntimeException('The Pay Code Treasury connection no longer matches its frozen source.');
            }
        }
    }
}
