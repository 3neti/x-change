<?php

namespace LBHurtado\Voucher\Data;

use LBHurtado\Voucher\Data\Transformers\TtlToStringTransformer;
use Spatie\LaravelData\Attributes\{WithCast, WithTransformer};
use LBHurtado\Voucher\Data\Casts\CarbonIntervalCast;
use LBHurtado\Voucher\Data\Traits\HasSafeDefaults;
use LBHurtado\Voucher\Enums\VoucherInputField;
use LBHurtado\Voucher\Rules\ValidISODuration;
use Propaganistas\LaravelPhone\Rules\Phone;
use Illuminate\Support\Number;
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

    public static function rules(): array
    {
        return [
            'cash.amount'     => 'required|numeric|min:0',
            'cash.currency'   => 'required|string|size:3',

            'cash.validation.secret'   => 'nullable|string',
            'cash.validation.mobile'   => ['nullable', (new Phone)->country('PH')->type('mobile')],
            'cash.validation.country'  => 'nullable|string|size:2',
            'cash.validation.location' => 'nullable|string',
            'cash.validation.radius'   => 'nullable|string',

            'inputs' => ['nullable', 'array'],
            'inputs.fields' => ['nullable', 'array'],
            'inputs.fields.*' => ['nullable', 'string', 'in:' . implode(',', VoucherInputField::values())],
            'feedback.mobile'   => ['nullable', (new Phone)->country('PH')->type('mobile')],
            'feedback.email'    => 'nullable|email',
            'feedback.webhook'  => 'nullable|url',

            'rider.message' => 'nullable|string|min:1',
            'rider.url'     => 'nullable|url',

            'count'  => 'required|integer|min:1',
            'prefix' => 'nullable|string|min:1',
            'mask'   => [
                'nullable',
                'string',
                function ($attribute, $value, $fail) {
                    if (!preg_match("/^[\*\-]+$/", $value)) {
                        $fail('The :attribute may only contain asterisks (*) and hyphens (-).');
                    }

                    $asterisks = substr_count($value, '*');

                    if ($asterisks < 4) {
                        $fail('The :attribute must contain at least 4 asterisks (*).');
                    }

                    if ($asterisks > 6) {
                        $fail('The :attribute must contain at most 6 asterisks (*).');
                    }
                },
            ],
            'ttl' => ['nullable', new ValidISODuration()],
            'starts_at'  => 'nullable|date',
            'expires_at' => 'nullable|date|after_or_equal:starts_at',
        ];
    }

    public static function createFromAttribs(array $attribs): VoucherInstructionsData
    {
        $validated = validator($attribs, static::rules())->validate();
        $data_array = [
            'cash' => [
                'amount' => $validated['cash']['amount'],
                'currency' => $validated['cash']['currency'],
                'validation' => [
                    'secret'   => $validated['cash']['validation']['secret'] ?? null,
                    'mobile'   => $validated['cash']['validation']['mobile'] ?? null,
                    'country'  => $validated['cash']['validation']['country'] ?? null,
                    'location' => $validated['cash']['validation']['location'] ?? null,
                    'radius'   => $validated['cash']['validation']['radius'] ?? null,
                ],
            ],
            'inputs' => [
                'fields' => $validated['inputs']['fields'] ?? null,
            ],
            'feedback' => [
                'email'   => $validated['feedback']['email'] ?? null,
                'mobile'  => $validated['feedback']['mobile'] ?? null,
                'webhook' => $validated['feedback']['webhook'] ?? null,
            ],
            'rider' => [
                'message' => $validated['rider']['message'] ?? '',
                'url'     => $validated['rider']['url'] ?? '',
            ],
            'count'      => $validated['count'],
            'prefix'     => $validated['prefix'] ?? '',
            'mask'       => $validated['mask'] ?? '',
            'ttl'        => $validated['ttl'] ?? null,
            'starts_at'  => $validated['starts_at'] ?? null,
            'expires_at' => $validated['expires_at'] ?? null,
        ];

        return VoucherInstructionsData::from($data_array);
    }

    public static function generateFromScratch(): VoucherInstructionsData
    {
        $data_array = [
            'cash' => [
                'amount' => 0,
                'currency' => Number::defaultCurrency(),
                'validation' => [
                    'secret' => null,
                    'mobile' => null,
                    'country' => config('instructions.cash.validation_rules.country'),
                    'location' => null,
                    'radius' => null,
                ],
            ],
            'inputs' => [
                'fields' => [],
            ],
            'feedback' => [
                'mobile' => null,
                'email' => null,
                'webhook' => null,
            ],
            'rider' => [
                'message' => null,
                'url' => null,
            ],
            'count' => 1, // New field for count
            'prefix' => null, // New field for prefix
            'mask' => null, // New field for mask
            'ttl' => null, // New field for ttl
        ];

        return VoucherInstructionsData::from($data_array);
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
                config('instructions.mask'),
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

    //TODO: move to a helper class (e.g., ArrayCleaner::clean())
    public function toCleanArray(): array
    {
        $array = $this->toArray();

        return self::cleanArray($array);
    }

    protected static function cleanArray(array $array): array
    {
        return collect($array)
            ->map(function ($value) {
                if (is_array($value)) {
                    // Recursively clean nested arrays
                    $cleaned = self::cleanArray($value);
                    return $cleaned;
                }

                // Leave scalars intact if not empty
                return $value;
            })
            ->filter(function ($value) {
                // Filter out only nulls and empty strings — keep empty arrays
                return !(is_null($value) || (is_string($value) && trim($value) === ''));
            })
            ->all();
    }
}

