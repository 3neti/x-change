<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Reserve;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LBHurtado\EmiCore\Data\Providers\AccountTransferRequestData;
use LBHurtado\XChange\Models\ReserveTransfer;

/** Atomically prepares the transfer and its principal, fee, and issuance commitments. */
final readonly class PrepareReserveBackedIssuance
{
    public function __construct(
        private DurableReserveTransfer $transfers,
        private ReserveTransferHold $holds,
        private ReserveIssuanceSnapshot $snapshots,
        private BuildReserveTransferRequest $requests,
    ) {}

    /** @param array<string, mixed> $instructions */
    public function prepareSelected(Model $owner, ?int $accountLinkId, int $amountMinor, string $currency, string $idempotencyKey, array $instructions): ReserveTransfer
    {
        $reference = $this->requests->referenceFor($owner, $idempotencyKey);
        $fingerprint = hash('sha256', json_encode([$owner->getMorphClass(), (string) $owner->getKey(), $accountLinkId, $amountMinor, $currency, $instructions], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($owner, $accountLinkId, $amountMinor, $currency, $idempotencyKey, $instructions, $reference, $fingerprint): ReserveTransfer {
            $existing = ReserveTransfer::query()->where('reference', $reference)->lockForUpdate()->first();
            if ($existing !== null) {
                if ($existing->owner_type !== $owner->getMorphClass() || (string) $existing->owner_id !== (string) $owner->getKey()
                    || ! is_string($existing->submission_fingerprint) || ! hash_equals($existing->submission_fingerprint, $fingerprint)) {
                    throw new \RuntimeException('The issuance key is already bound to different instructions.');
                }

                return $existing;
            }
            $built = $this->requests->build($owner, $accountLinkId, $amountMinor, $currency, $idempotencyKey);
            $transfer = $this->prepare($built['request'], $built['source_connection'], $built['reserve_connection'], $owner, $built['account_link_id'], $instructions);
            $transfer->forceFill(['submission_fingerprint' => $fingerprint])->save();

            return $transfer;
        }, attempts: 3);
    }

    /** @param array<string, mixed> $instructions */
    public function prepare(AccountTransferRequestData $request, string $sourceConnection, string $reserveConnection, Model $owner, int $accountLinkId, array $instructions): ReserveTransfer
    {
        return DB::transaction(function () use ($request, $sourceConnection, $reserveConnection, $owner, $accountLinkId, $instructions): ReserveTransfer {
            $transfer = $this->transfers->prepare($request, $sourceConnection, $reserveConnection);
            $transfer = $this->holds->hold($transfer, $owner, $accountLinkId);

            return $this->snapshots->bind($transfer, $owner, $instructions);
        }, attempts: 3);
    }
}
