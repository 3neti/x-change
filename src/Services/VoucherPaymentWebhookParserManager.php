<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\XChange\Contracts\VoucherPaymentWebhookParserContract;
use LBHurtado\XChange\Services\PaymentWebhooks\ManualVoucherPaymentWebhookParser;
use RuntimeException;

class VoucherPaymentWebhookParserManager
{
    public function driver(?string $name = null): VoucherPaymentWebhookParserContract
    {
        $name = $name ?: (string) config('x-change.payment.default_provider', 'manual');

        return match ($name) {
            'manual' => app(ManualVoucherPaymentWebhookParser::class),
            default => $this->custom($name),
        };
    }

    protected function custom(string $name): VoucherPaymentWebhookParserContract
    {
        $parsers = (array) config('x-change.payment.webhook_parsers', []);
        $class = $parsers[$name] ?? null;

        if (! is_string($class) || $class === '' || ! class_exists($class)) {
            throw new RuntimeException("Unsupported voucher payment webhook parser [{$name}].");
        }

        $parser = app($class);

        if (! $parser instanceof VoucherPaymentWebhookParserContract) {
            throw new RuntimeException("Voucher payment webhook parser [{$name}] must implement VoucherPaymentWebhookParserContract.");
        }

        return $parser;
    }
}
