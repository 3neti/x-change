<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Banking;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LBHurtado\XChange\Models\DisbursementReconciliation;

final class PayoutBankSourceSnapshot
{
    /** @param Closure(): array<string, mixed> $resolve
     * @return array<string, mixed>
     */
    public function remember(int $payoutId, Closure $resolve): array
    {
        return DB::transaction(function () use ($payoutId, $resolve): array {
            $payout = DisbursementReconciliation::query()->lockForUpdate()->findOrFail($payoutId);
            if ($payout->bank_source_snapshot !== null) {
                return $payout->bank_source_snapshot;
            }

            $snapshot = array_intersect_key($resolve(), array_flip([
                'provider', 'account_number', 'customer_id', 'mode', 'account_link_id',
                'treasury_connection_reference', 'treasury_inventory_reference', 'treasury_resource_reference',
            ]));
            if (! filled($snapshot['provider'] ?? null) || ! filled($snapshot['account_number'] ?? null)
                || ! filled($snapshot['customer_id'] ?? null)) {
                throw ValidationException::withMessages(['account' => 'The payout source account is incomplete.']);
            }

            $payout->forceFill(['bank_source_snapshot' => $snapshot])->save();

            return $snapshot;
        }, attempts: 5);
    }
}
