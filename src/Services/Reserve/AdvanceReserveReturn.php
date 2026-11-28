<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Reserve;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\EmiCore\Contracts\AccountTransferProvider;
use LBHurtado\XChange\Models\ReserveTransfer;
use RuntimeException;

final readonly class AdvanceReserveReturn
{
    public function __construct(
        private DurableReserveTransfer $transfers,
        private ReserveReturnTreasuryPosting $posting,
        private ReserveTransferRelease $release,
    ) {}

    public function advance(ReserveTransfer $return, Model $owner, AccountTransferProvider $provider): ReserveTransfer
    {
        $current = $return->fresh();
        if ($current->transfer_kind !== 'return' || $current->owner_type !== $owner->getMorphClass()
            || (string) $current->owner_id !== (string) $owner->getKey()
            || $current->provider !== $provider->providerCode()) {
            throw new RuntimeException('The reserve return is unavailable for this account.');
        }
        if ($current->status === 'held') {
            return $this->transfers->submit($current, $provider);
        }
        if ($current->status === 'pending') {
            $current = $this->transfers->check($current, $provider);
        }
        if ($current->status === 'rejected') {
            return $this->release->release($current);
        }
        if ($current->status === 'provider_settled') {
            return $this->posting->post($current);
        }

        return $current;
    }
}
