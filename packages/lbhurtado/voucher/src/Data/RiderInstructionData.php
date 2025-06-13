<?php

namespace LBHurtado\Voucher\Data;

use LBHurtado\Voucher\Data\Traits\HasSafeDefaults;
use Spatie\LaravelData\Data;

class RiderInstructionData extends Data
{
    use HasSafeDefaults;

    public function __construct(
        public ?string $message,
        public ?string $url,
    ) { $this->applyRulesAndDefaults(); }

    protected function rulesAndDefaults(): array
    {
        return [
            'message' => [
                ['required', 'string', 'max:255'],
                config('instructions.rider.message')
            ],
            'url' => [
                ['required', 'url', 'max:2048'],
                config('instructions.rider.url')
            ]
        ];
    }
}
