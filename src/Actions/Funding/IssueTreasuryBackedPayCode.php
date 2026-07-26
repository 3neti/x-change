<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use FrittenKeeZ\Vouchers\Facades\Vouchers;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Enums\VoucherState;
use LBHurtado\Voucher\Enums\VoucherType;
use LBHurtado\Voucher\Models\Voucher;
use RuntimeException;

final class IssueTreasuryBackedPayCode
{
    /**
     * @param  array<string, mixed>  $instructions
     */
    public function handle(
        Authenticatable&Model $issuer,
        array $instructions,
        Carbon $expiresAt,
        VoucherState $initialState = VoucherState::ACTIVE,
    ): Voucher {
        $data = VoucherInstructionsData::createFromAttribs($instructions);
        $created = Vouchers::withPrefix($data->prefix ?? 'FUND')
            ->withMask($data->mask ?? '****')
            ->withMetadata(['instructions' => $data->toCleanArray()])
            ->withOwner($issuer)
            ->withExpireTime($expiresAt)
            ->create(1);
        $voucher = Collection::wrap($created)->sole();

        if (! $voucher instanceof Voucher) {
            throw new RuntimeException('Treasury-backed Pay Code issuance failed.');
        }

        $voucher->forceFill([
            'voucher_type' => $data->voucher_type ?? VoucherType::REDEEMABLE,
            'state' => $initialState,
            'processed_on' => now(),
        ])->save();

        return $voucher->refresh();
    }
}
