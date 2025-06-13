<?php

namespace App\Actions;

use App\Services\InstructionParser;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use LBHurtado\Voucher\Actions\GenerateVouchers;
use Lorisleiva\Actions\Concerns\AsAction;

class CutCheck
{
    use AsAction;

    public function __construct(
        protected InstructionParser $parser
    ) {}

    /**
     * @param  string  $text  The human instruction text
     * @return \Illuminate\Support\Collection  The newly generated vouchers
     */
    public function handle(string $text): Collection
    {
        Log::debug('[CutCheck] Received raw text for parsing', ['text' => $text]);

        $instructions = $this->parser->fromText($text);

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
