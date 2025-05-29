<?php

namespace LBHurtado\Voucher\Actions;

use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Events\VouchersGenerated;
use FrittenKeeZ\Vouchers\Facades\Vouchers;
use Lorisleiva\Actions\Concerns\AsAction;
use LBHurtado\Voucher\Models\Voucher;
use Illuminate\Support\Collection;
use Carbon\CarbonInterval;

class GenerateVouchers
{
    use AsAction;

    public function handle(VoucherInstructionsData $instructions): Collection
    {
        // Extract parameters from instructions
        $count = $instructions->count ?? 1; // Use 'count' from instructions or default to 1
        $prefix = $instructions->prefix ?? config('lbhurtado-voucher.default-prefix', '');
        $mask = $instructions->mask ?? config('lbhurtado-voucher.default-mask', '******');
        $ttl = $instructions->ttl instanceof CarbonInterval
            ? $instructions->ttl
            : CarbonInterval::hours(12); // Default TTL to 12 hours if missing

        $owner = auth()->user();
        if (is_null($owner)) {
            throw new \Exception('No authenticated user found. Please ensure a user is logged in.');
        }

        $vouchers = Vouchers::withPrefix($prefix)
            ->withMask($mask)
            ->withMetadata(['instructions' => $instructions->toArray()]) // Pass instructions as metadata
            ->withExpireTimeIn($ttl)
            ->withOwner($owner)
            ->create($count)
        ;

        // Normalize the vouchers collection (for single vs. multiple)
        $collection = collect($vouchers instanceof Voucher ? [$vouchers] : $vouchers);

        // Dispatch the event with the generated vouchers
        VouchersGenerated::dispatch($collection);

        return $collection;
    }
}
