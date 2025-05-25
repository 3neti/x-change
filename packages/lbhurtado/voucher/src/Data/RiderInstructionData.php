<?php

namespace LBHurtado\Voucher\Data;

use Spatie\LaravelData\Data;
class RiderInstructionData extends Data
{
    public function __construct(
        public string $message,
        public string $url,
    ) {}

    public static function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:255'],
            'url' => ['nullable', 'url', 'max:2048'],
        ];
    }
}
