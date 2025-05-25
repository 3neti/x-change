<?php

namespace LBHurtado\Voucher\Actions;

use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Events\VouchersGenerated;
use FrittenKeeZ\Vouchers\Facades\Vouchers;
use Lorisleiva\Actions\Concerns\AsAction;
use FrittenKeeZ\Vouchers\Models\Voucher;
use Illuminate\Support\Collection;
use Carbon\CarbonInterval;

class GenerateVouchers
{
    use AsAction;

    public function handle(
        VoucherInstructionsData $data,
        ?int $count = null,
        ?string $prefix = null,
        ?string $mask = null,
        CarbonInterval|string|null $ttl = null
    ): Collection {
        $count ??= 1;
        $prefix ??= config('lbhurtado-voucher.default-prefix', '');
        $mask   ??= config('lbhurtado-voucher.default-mask', '******');
        $ttl = match (true) {
            $ttl instanceof CarbonInterval => $ttl,
            is_string($ttl)                => new CarbonInterval($ttl),
            default                        => CarbonInterval::hours(12),
        };

        $vouchers = Vouchers::withPrefix($prefix)
            ->withMask($mask)
            ->withMetadata(['instructions' => $data->toArray()])
            ->withExpireTimeIn($ttl)
            ->create($count);

        $collection = collect($vouchers instanceof Voucher ? [$vouchers] : $vouchers);

        VouchersGenerated::dispatch($collection);

        return $collection;
    }
}
