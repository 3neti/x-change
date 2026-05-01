<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\XChange\Data\Payment\VoucherPaymentResultData;

interface VoucherPaymentWebhookParserContract
{
    public function parse(array $payload): VoucherPaymentResultData;

    public function voucherCode(array $payload): ?string;
}
