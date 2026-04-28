<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Payment;

use Spatie\LaravelData\Data;

class RenderedVoucherPaymentQrData extends Data
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $voucher_code,
        public string $format,
        public string $content_type,
        public array $payload,
        public ?string $rendered = null,
    ) {}
}
