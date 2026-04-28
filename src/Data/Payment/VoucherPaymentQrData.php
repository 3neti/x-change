<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Payment;

use Spatie\LaravelData\Data;

class VoucherPaymentQrData extends Data
{
    /**
     * @param  array<string, mixed>  $capabilities
     */
    public function __construct(
        public string $voucher_code,
        public string $flow_type,
        public string $route_key,
        public string $url,
        public string $qr_type,
        public array $capabilities = [],
    ) {}
}
