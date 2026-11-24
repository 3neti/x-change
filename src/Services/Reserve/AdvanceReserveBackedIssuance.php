<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Reserve;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LBHurtado\EmiCore\Contracts\AccountTransferProvider;
use LBHurtado\XChange\Models\ReserveTransfer;
use RuntimeException;

/** Advances only states for which the bank and treasury evidence are already durable. */
final readonly class AdvanceReserveBackedIssuance
{
    public function __construct(
        private DurableReserveTransfer $transfers,
        private ReserveTransferTreasuryPosting $posting,
        private IssueReserveBackedPayCode $issuance,
        private ReserveTransferRelease $release,
    ) {}

    public function recoverInterrupted(ReserveTransfer $transfer, Model $owner): ReserveTransfer
    {
        $current = $transfer->fresh();
        if ($current->owner_type !== $owner->getMorphClass() || (string) $current->owner_id !== (string) $owner->getKey()) {
            throw new RuntimeException('The reserve transfer is unavailable for this account.');
        }

        return $this->transfers->markInterruptedUncertain($current);
    }

    public function advance(ReserveTransfer $transfer, Model $owner, AccountTransferProvider $provider): ReserveTransfer
    {
        $current = $transfer->fresh();
        if ($current->owner_type !== $owner->getMorphClass() || (string) $current->owner_id !== (string) $owner->getKey()
            || $current->provider !== $provider->providerCode()) {
            throw new RuntimeException('The reserve transfer is unavailable for this account.');
        }
        if ($current->status === 'held') {
            return $this->transfers->submit($current, $provider);
        }
        if ($current->status === 'pending') {
            $current = $this->transfers->check($current, $provider);
        }
        if ($current->status === 'rejected') {
            if ($current->funding_order_id !== null) {
                return DB::transaction(function () use ($current): ReserveTransfer {
                    $locked = ReserveTransfer::query()->lockForUpdate()->findOrFail($current->getKey());
                    app(ReserveFundingOrderLink::class)->markRejected($locked);

                    return $locked;
                });
            }

            return $this->release->release($current);
        }
        if ($current->status === 'provider_settled') {
            $current = $this->posting->post($current);
        }
        if ($current->status === 'treasury_posted') {
            $this->issuance->issue($current, $owner);

            return $current->refresh();
        }

        return $current;
    }
}
