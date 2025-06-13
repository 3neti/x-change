<?php

namespace LBHurtado\Voucher\Data;

use LBHurtado\Voucher\Data\Traits\HasSafeDefaults;
use LBHurtado\Voucher\Enums\VoucherInputField;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

/**
 * @property array<VoucherInputField> $fields
 */
class InputFieldsData extends Data
{
    use HasSafeDefaults;

    /**
     * @param array<VoucherInputField> $fields
     */
    public function __construct(
        #[WithCast(EnumCast::class, VoucherInputField::class)]
        public array $fields
    ) {
        $this->applyRulesAndDefaults();
//        $this->fields = empty($fields) ? config('instructions.input_fields') : $fields;
    }

    public static function fromArray(array $input): self
    {
        $fields = $input['fields'] ?? $input;

        return new self(array_map(
            fn ($field) => VoucherInputField::from($field),
            $fields
        ));
    }

    public function contains(VoucherInputField $field): bool
    {
        return in_array($field, $this->fields, true);
    }

    public function toArray(): array
    {
        return array_map(fn ($field) => $field->value, $this->fields);
    }

    public static function rules(): array
    {
        return [
            'fields' => ['required', 'array'],
            'fields.*' => ['string', 'in:' . implode(',', array_column(VoucherInputField::cases(), 'value'))],
        ];
    }

    protected function rulesAndDefaults(): array
    {
        return [
            'fields' => [
                // reuse the same rules as above
                static::rules()['fields'],
                // default comes from config, e.g. ['email','mobile','reference_code']
                config('instructions.input_fields'),
            ],
        ];
    }
}
