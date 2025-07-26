<?php

namespace App\Actions;

use LBHurtado\Voucher\Actions\GenerateVouchers;
use Lorisleiva\Actions\Concerns\AsAction;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class CutCheck
{
    use AsAction;

    /**
     * @param  string  $text  The human instruction text
     * @return \Illuminate\Support\Collection  The newly generated vouchers
     * @todo explicitly add owner in the parameter
     */
    public function handle(string $text): Collection
    {
        Log::debug('[CutCheck] Received raw text for parsing', ['text' => $text]);

        $instructions = ParseInstructions::run($text);

        Log::debug('[CutCheck] Parsed instructions', [
            'instructions' => $instructions->toArray(),
        ]);

        Log::debug('[CutCheck] Dispatching GenerateVouchers', [
            'count'  => $instructions->count,
            'prefix' => $instructions->prefix,
            'mask'   => $instructions->mask,
            'ttl'    => (string) $instructions->ttl,
        ]);

        $vouchers = GenerateVouchers::run($instructions);

        Log::debug('[CutCheck] Generated vouchers', [
            'codes' => $vouchers->pluck('code')->all(),
            'count' => $vouchers->count(),
        ]);

        return $vouchers;
    }
}
