<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Closure;
use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Models\DisbursementReconciliation;

final class PayoutLocalitySnapshot
{
    /** @param Closure(): array<string, mixed> $resolve
     * @return array<string, mixed>
     */
    public function remember(int $payoutId, Closure $resolve): array
    {
        return DB::transaction(function () use ($payoutId, $resolve): array {
            $payout = DisbursementReconciliation::query()->lockForUpdate()->findOrFail($payoutId);
            if ($payout->payout_identity_snapshot !== null) {
                return $payout->payout_identity_snapshot;
            }
            $snapshot = $resolve();
            $payout->forceFill(['payout_identity_snapshot' => $snapshot])->save();

            return $snapshot;
        }, 5);
    }
}
