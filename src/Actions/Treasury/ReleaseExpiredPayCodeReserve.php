<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Treasury;

use Illuminate\Support\Facades\DB;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\XChange\Data\Treasury\PayCodeTerminalReleaseData;
use LBHurtado\XChange\Exceptions\TreasuryConfigurationException;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Models\VoucherClaim;

final readonly class ReleaseExpiredPayCodeReserve
{
    public function __construct(
        private ReleasePayCodeTerminalReserve $terminalRelease,
    ) {}

    public function handle(Voucher $voucher): PayCodeTerminalReleaseData
    {
        return DB::transaction(function () use ($voucher): PayCodeTerminalReleaseData {
            $locked = Voucher::query()
                ->lockForUpdate()
                ->findOrFail($voucher->getKey());

            $existingRelease = data_get($locked->metadata, 'treasury.terminal_release');

            if (is_array($existingRelease)) {
                if (data_get($existingRelease, 'terminal_reason') !== 'expired') {
                    throw new TreasuryConfigurationException(
                        "Pay Code [{$locked->code}] already has a different terminal release.",
                    );
                }

                return $this->terminalRelease->handle($locked, 'expired');
            }

            $this->assertEligible($locked);

            return $this->terminalRelease->handle($locked, 'expired');
        }, attempts: 5);
    }

    private function assertEligible(Voucher $voucher): void
    {
        if (! $voucher->isExpired() || $voucher->isClosed()) {
            throw new TreasuryConfigurationException(
                "Pay Code [{$voucher->code}] is not an open expired Pay Code.",
            );
        }

        if (
            $voucher->redeemed_at !== null
            || VoucherClaim::query()->where('voucher_id', $voucher->getKey())->exists()
        ) {
            throw new TreasuryConfigurationException(
                "Pay Code [{$voucher->code}] has a claim and cannot return principal to Client Funds.",
            );
        }

        if (DisbursementReconciliation::query()
            ->where('voucher_id', $voucher->getKey())
            ->exists()) {
            throw new TreasuryConfigurationException(
                "Pay Code [{$voucher->code}] has payout activity and cannot be released by expiry.",
            );
        }

        $reservation = data_get($voucher->metadata, 'treasury.pay_code_reservation');

        if (! is_array($reservation) || data_get($reservation, 'status') !== 'reserved') {
            throw new TreasuryConfigurationException(
                "Pay Code [{$voucher->code}] has no eligible reserved principal.",
            );
        }

        $sourcePurpose = (string) data_get(
            $reservation,
            'source_position_purpose',
            TreasuryPositionPurpose::ClientFunds->value,
        );

        if ($sourcePurpose !== TreasuryPositionPurpose::ClientFunds->value) {
            throw new TreasuryConfigurationException(
                "Pay Code [{$voucher->code}] was not reserved from Client Funds.",
            );
        }

        if (
            data_get($voucher->metadata, 'disbursement.requires_recovery') === true
            || in_array(
                data_get($voucher->metadata, 'disbursement.status'),
                ['pending', 'processing', 'queued', 'accepted', 'submitted'],
                true,
            )
        ) {
            throw new TreasuryConfigurationException(
                "Pay Code [{$voucher->code}] has protected payout or recovery activity.",
            );
        }
    }
}
