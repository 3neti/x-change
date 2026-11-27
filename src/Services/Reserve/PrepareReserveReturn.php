<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Reserve;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Models\ReserveTransfer;
use RuntimeException;

/** Prepares one return per terminal dedicated-account Pay Code. */
final readonly class PrepareReserveReturn
{
    public function __construct(
        private BuildReserveReturnRequest $requests,
        private DurableReserveTransfer $transfers,
        private ReserveReturnHold $holds,
    ) {}

    public function prepare(Voucher $voucher, string $terminalReason): ?ReserveTransfer
    {
        if (! in_array($terminalReason, ['cancelled', 'expired'], true)) {
            throw new RuntimeException('An escrow return requires a terminal Pay Code state.');
        }

        $origin = ReserveTransfer::query()
            ->where('transfer_kind', 'issuance')
            ->where('voucher_id', $voucher->getKey())
            ->first();
        $owner = $voucher->owner;
        if (! $origin instanceof ReserveTransfer || ! $owner instanceof Model) {
            return null;
        }
        $built = $this->requests->build($origin, $terminalReason);
        $return = $this->transfers->prepare(
            $built['request'],
            $built['source_connection'],
            $built['destination_connection'],
        );

        $return = DB::transaction(function () use ($return, $origin, $owner, $terminalReason): ReserveTransfer {
            $locked = ReserveTransfer::query()->lockForUpdate()->findOrFail($return->getKey());
            if ($locked->transfer_kind === 'return') {
                return $locked;
            }
            if ($locked->transfer_kind !== 'issuance' || $locked->status !== 'prepared') {
                throw new RuntimeException('The terminal return reference is already bound to another transfer.');
            }
            $locked->forceFill([
                'transfer_kind' => 'return',
                'origin_reserve_transfer_id' => $origin->getKey(),
                'terminal_reason' => $terminalReason,
                'owner_type' => $owner->getMorphClass(),
                'owner_id' => (string) $owner->getKey(),
                'bank_account_link_id' => $origin->bank_account_link_id,
            ])->save();

            return $locked->refresh();
        }, attempts: 3);

        return $return->status === 'prepared' ? $this->holds->hold($return, $owner) : $return;
    }
}
