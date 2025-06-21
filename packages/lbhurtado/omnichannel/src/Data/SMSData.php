<?php

namespace LBHurtado\OmniChannel\Data;

use Spatie\LaravelData\Data;

class SMSData extends Data
{
    public function __construct(
        public string $from,
        public string $to,
        public string $message
    ) {}

    public static function rules(): array
    {
        return [
            'from' => ['required', (new \Propaganistas\LaravelPhone\Rules\Phone)->country('PH')->type('mobile')],
            'to' => ['required', 'string'],
            'message' => ['required', 'string'],
        ];
    }
}
