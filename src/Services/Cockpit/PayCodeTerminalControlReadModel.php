<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use LBHurtado\Voucher\Enums\VoucherState;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Models\VoucherClaim;

final readonly class PayCodeTerminalControlReadModel
{
    /**
     * @return array<string, mixed>
     */
    public function forVoucher(
        Voucher $voucher,
        ?Authenticatable $actor,
        ?bool $knownHasClaim = null,
        ?bool $knownHasPayout = null,
    ): array {
        $reservation = data_get($voucher->metadata, 'treasury.pay_code_reservation');
        $reservation = is_array($reservation) ? $reservation : [];
        $sourcePurpose = (string) data_get(
            $reservation,
            'source_position_purpose',
            TreasuryPositionPurpose::ClientFunds->value,
        );
        $reservationStatus = (string) data_get($reservation, 'status', '');
        $hasClaim = $voucher->redeemed_at !== null
            || $knownHasClaim
            || ($knownHasClaim === null
                && VoucherClaim::query()->where('voucher_id', $voucher->getKey())->exists());
        $hasPayout = $knownHasPayout
            ?? DisbursementReconciliation::query()
                ->where('voucher_id', $voucher->getKey())
                ->exists();
        $hasProtectedRecovery = data_get($voucher->metadata, 'disbursement.requires_recovery') === true
            || in_array(
                data_get($voucher->metadata, 'disbursement.status'),
                ['pending', 'processing', 'queued', 'accepted', 'submitted'],
                true,
            );
        $owner = $voucher->owner;
        $isOwner = $actor instanceof Model
            && $owner instanceof Model
            && $owner->is($actor);
        $isOpen = in_array($voucher->state, [VoucherState::ACTIVE, VoucherState::LOCKED], true)
            && ! $voucher->isExpired();
        $isRegularReservation = $sourcePurpose === TreasuryPositionPurpose::ClientFunds->value;
        $canTerminate = $isOwner
            && $isOpen
            && $isRegularReservation
            && $reservationStatus === 'reserved'
            && ! $hasClaim
            && ! $hasPayout
            && ! $hasProtectedRecovery;

        return [
            'schema' => 'x-change.cockpit.pay-code-terminal-control.v1',
            'authorized' => $isOwner,
            'status' => $canTerminate ? 'available' : 'blocked',
            'can_expire' => $canTerminate,
            'can_cancel' => $canTerminate,
            'blocked_reason' => $canTerminate ? null : $this->blockedReason(
                isOwner: $isOwner,
                isOpen: $isOpen,
                isRegularReservation: $isRegularReservation,
                reservationStatus: $reservationStatus,
                hasClaim: $hasClaim,
                hasPayout: $hasPayout,
                hasProtectedRecovery: $hasProtectedRecovery,
            ),
            'release' => [
                'amount_minor' => (int) data_get($reservation, 'amount_minor', 0),
                'currency' => (string) data_get($reservation, 'currency', 'PHP'),
                'from' => 'Pay Code Reserve',
                'to' => 'Client Funds',
                'provider_inventory_changed' => false,
                'provider_calls' => false,
                'issuance_charges_refunded' => false,
            ],
            'history' => collect(data_get($voucher->metadata, 'lifecycle.terminal_actions', []))
                ->filter(fn (mixed $event): bool => is_array($event))
                ->map(fn (array $event): array => [
                    'action' => (string) data_get($event, 'action', 'terminal'),
                    'reason' => $this->nullableString(data_get($event, 'reason')),
                    'occurred_at' => $this->nullableString(data_get($event, 'occurred_at')),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  Collection<int|string, Voucher>  $vouchers
     * @return array<int|string, array<string, mixed>>
     */
    public function forVouchers(Collection $vouchers, ?Authenticatable $actor): array
    {
        $ids = $vouchers->modelKeys();

        if ($ids === []) {
            return [];
        }

        $claimedVoucherIds = VoucherClaim::query()
            ->whereIn('voucher_id', $ids)
            ->distinct()
            ->pluck('voucher_id')
            ->mapWithKeys(fn (mixed $id): array => [(string) $id => true]);
        $payoutVoucherIds = DisbursementReconciliation::query()
            ->whereIn('voucher_id', $ids)
            ->distinct()
            ->pluck('voucher_id')
            ->mapWithKeys(fn (mixed $id): array => [(string) $id => true]);

        return $vouchers->mapWithKeys(fn (Voucher $voucher): array => [
            (string) $voucher->getKey() => $this->forVoucher(
                voucher: $voucher,
                actor: $actor,
                knownHasClaim: $claimedVoucherIds->has((string) $voucher->getKey()),
                knownHasPayout: $payoutVoucherIds->has((string) $voucher->getKey()),
            ),
        ])->all();
    }

    private function blockedReason(
        bool $isOwner,
        bool $isOpen,
        bool $isRegularReservation,
        string $reservationStatus,
        bool $hasClaim,
        bool $hasPayout,
        bool $hasProtectedRecovery,
    ): string {
        return match (true) {
            ! $isOwner => 'Only the Pay Code owner may use terminal controls.',
            $hasClaim => 'A claim is already recorded; the principal remains protected for settlement.',
            $hasPayout || $hasProtectedRecovery => 'Payout or recovery activity prevents an issuer-side release.',
            ! $isRegularReservation => 'This Pay Code was not reserved from ordinary Client Funds.',
            $reservationStatus !== 'reserved' => 'No releasable Pay Code Reserve remains.',
            ! $isOpen => 'This Pay Code is already terminal or no longer available.',
            default => 'Terminal controls are not available.',
        };
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }
}
