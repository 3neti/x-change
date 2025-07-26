<?php

namespace LBHurtado\Voucher\Actions;

use LBHurtado\Voucher\Data\VoucherInstructionsData;
use Illuminate\Support\Facades\{Log, Validator};
use LBHurtado\Voucher\Events\VouchersGenerated;
use FrittenKeeZ\Vouchers\Facades\Vouchers;
use Lorisleiva\Actions\Concerns\AsAction;
use Illuminate\Support\Collection;
use Carbon\CarbonInterval;

class GenerateVouchers
{
    use AsAction;

    //TODO: explicitly add owner in the parameter
    public function handle(VoucherInstructionsData|array $instructions): Collection
    {
        if (is_array($instructions)) {
            $instructions = VoucherInstructionsData::createFromAttribs($instructions);
        }

        Log::debug('[GenerateVouchers] Received count=' . $instructions->count);
        Log::debug('[GenerateVouchers] Raw DTO:', $instructions->toArray());

        // Extract parameters from instructions
        $count = $instructions->count ?? 1; // Use 'count' from instructions or default to 1
        $prefix = $instructions->prefix ?? config('x-change.generate.prefix');
        $mask = $instructions->mask ?? config('x-change.generate.mask');

        // Validate or fallback the mask
        $validator = Validator::make(['mask' => $mask], [
            'mask' => ['required', 'string', 'min:4', 'regex:/\*/'],
        ]);

        if ($validator->fails()) {
            Log::warning('[GenerateVouchers] Invalid mask provided. Using default mask.');
            $mask = '****';
        }

        $ttl = $instructions->ttl instanceof CarbonInterval
            ? $instructions->ttl
            : CarbonInterval::hours(12); // Default TTL to 12 hours if missing

        Log::debug('[GenerateVouchers] About to create', compact('count','prefix','mask','ttl'));

        $owner = auth()->user();
        if (is_null($owner)) {
            throw new \Exception('No authenticated user found. Please ensure a user is logged in.');
        }

        $vouchers = Vouchers::withPrefix($prefix)
            ->withMask($mask)
            ->withMetadata(['instructions' => $instructions->toCleanArray()]) // This is most important! Pass instructions as metadata.
            ->withExpireTimeIn($ttl)
            ->withOwner($owner)
            ->create($count)
        ;
        Log::debug('[GenerateVouchers] Raw facade response', ['raw' => $vouchers]);

        /** @var \Illuminate\Support\Collection<int, \FrittenKeeZ\Vouchers\Models\Voucher> $collection */
        $collection = collect(is_array($vouchers) ? $vouchers : [$vouchers]);

        Log::debug('[GenerateVouchers] Normalized voucher list', [
            'count' => $collection->count(),
            'codes' => $collection->pluck('code')->all(),
        ]);

        // Dispatch the event with the generated vouchers
        VouchersGenerated::dispatch($collection);

        return $collection;
    }
}
