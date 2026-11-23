<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Banking;

use Illuminate\Support\Facades\DB;
use LBHurtado\EmiCore\Data\PayoutSourceAccountData;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Models\BankAccountAssignment;
use LBHurtado\XChange\Models\VoucherBankSource;
use RuntimeException;

final class VoucherBankSourceSnapshot
{
    /** @return array<string, mixed> */
    public function freeze(Voucher $voucher, int $accountLinkId, bool $requireTreasury = false): array
    {
        return DB::transaction(function () use ($voucher, $accountLinkId, $requireTreasury): array {
            $locked = Voucher::query()->lockForUpdate()->findOrFail($voucher->getKey());
            $existing = VoucherBankSource::query()->where('voucher_id', $locked->getKey())->first();
            if ($existing !== null) {
                if ((int) ($existing->source_ciphertext['account_link_id'] ?? 0) !== $accountLinkId) {
                    throw new RuntimeException('The Pay Code already has a different payout source.');
                }

                if ($requireTreasury && ! filled($existing->source_ciphertext['treasury_connection_reference'] ?? null)) {
                    throw new RuntimeException('The Pay Code payout source has no Treasury connection.');
                }

                return $existing->source_ciphertext;
            }

            $assignment = BankAccountAssignment::query()->whereMorphedTo('owner', $locked->owner)
                ->where('provider_account_link_id', $accountLinkId)->first();
            $details = $assignment?->details_ciphertext;
            if (! is_array($details) || ! filled($details['account_number'] ?? null) || ! filled($details['customer_id'] ?? null)) {
                throw new RuntimeException('The selected payout source is unavailable.');
            }
            $source = app(AuthorizedPayoutBankSource::class)->resolve($locked->owner, new PayoutSourceAccountData(
                $assignment->provider, $details['provider_account_number'] ?? $details['account_number'], $details['customer_id'],
            ));
            if ((int) $source['account_link_id'] !== $accountLinkId) {
                throw new RuntimeException('The selected payout source is unavailable.');
            }
            if ($requireTreasury && $assignment->treasury_connection_reference === null) {
                throw new RuntimeException('The selected payout source has no Treasury connection.');
            }
            if ($assignment->treasury_connection_reference !== null) {
                $connection = app(AssignedBankTreasuryConnection::class)->resolve($locked->owner, $accountLinkId);
                $source['treasury_connection_reference'] = $connection->reference;
                $source['treasury_inventory_reference'] = $connection->inventoryReference;
                $source['treasury_resource_reference'] = $connection->settlementResourceReference;
            }
            VoucherBankSource::query()->create(['voucher_id' => $locked->getKey(), 'source_ciphertext' => $source]);

            return $source;
        }, attempts: 3);
    }

    /** @return array<string, mixed>|null */
    public function forVoucher(Voucher $voucher): ?array
    {
        return VoucherBankSource::query()->where('voucher_id', $voucher->getKey())->first()?->source_ciphertext;
    }
}
