<?php

namespace LBHurtado\Voucher\Data;

use Spatie\LaravelData\Data;
class FeedbackInstructionData extends Data
{
    public function __construct(
        public ?string $email = null,
        public ?string $mobile = null,
        public ?string $webhook = null,
    ) {}

    public static function rules(): array
    {
        return [
            'email' => ['nullable', 'email'],
            'mobile' => ['nullable', 'regex:/^09\d{9}$/'],
            'webhook' => ['nullable', 'url'],
        ];
    }
}
