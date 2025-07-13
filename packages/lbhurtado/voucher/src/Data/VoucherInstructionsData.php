<?php

namespace LBHurtado\Voucher\Data;

use LBHurtado\Voucher\Data\Transformers\TtlToStringTransformer;
use Spatie\LaravelData\Attributes\{WithCast, WithTransformer};
use LBHurtado\Voucher\Data\Casts\CarbonIntervalCast;
use LBHurtado\Voucher\Data\Traits\HasSafeDefaults;
use Spatie\LaravelData\Data;
use Carbon\CarbonInterval;

class VoucherInstructionsData extends Data
{
    use HasSafeDefaults;

    public function __construct(
        public CashInstructionData     $cash,
        public InputFieldsData         $inputs,
        public FeedbackInstructionData $feedback,
        public RiderInstructionData    $rider,
        public ?int                    $count,            // Number of vouchers to generate
        public ?string                 $prefix,           // Prefix for voucher codes
        public ?string                 $mask,             // Mask for voucher codes
        #[WithTransformer(TtlToStringTransformer::class)]
        #[WithCast(CarbonIntervalCast::class)]
        public CarbonInterval|null     $ttl,              // Expiry time (TTL)
    ){
        $this->applyRulesAndDefaults();
//        $this->ttl = $ttl ?: CarbonInterval::hours(config('instructions.ttl'));
    }

    protected function rulesAndDefaults(): array
    {
        return [
            'count' => [
                ['required', 'integer', 'min:1'],
                config('instructions.count', 1),
            ],
            'prefix' => [
                ['required', 'string', 'min:1', 'max:10'],
                config('instructions.prefix', ''),
            ],
            'mask' => [
                ['required', 'string', 'min:3', 'regex:/\*/'],
                config('instructions.mask', ''),
            ],
//            'ttl' => [
//                // nullable ISO-8601 duration format:
//                ['required', 'string',
//                    // this regex loosely matches e.g. P1DT2H30M or PT12H
//                    'regex:/^P(?!$)(\d+Y)?(\d+M)?(\d+W)?(\d+D)?(T(\d+H)?(\d+M)?(\d+S)?)?$/'
//                ],
//                // default to 12 hours (or pull from config('instructions.ttl','PT12H'))
//                CarbonInterval::hours(config('instructions.ttl', 12)),
//            ],
        ];
    }
}

