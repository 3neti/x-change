<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Illuminate\Contracts\Container\Container;
use LBHurtado\XChange\Contracts\VoucherPaymentQrRendererContract;
use RuntimeException;

class VoucherPaymentQrRendererFactory
{
    public function __construct(
        protected Container $container,
    ) {}

    public function make(?string $driver = null): VoucherPaymentQrRendererContract
    {
        $driver ??= (string) config('x-change.payment_qr.renderer', 'json');

        $renderers = (array) config('x-change.payment_qr.renderers', []);

        $class = $renderers[$driver] ?? null;

        if (! is_string($class) || $class === '') {
            throw new RuntimeException("Unsupported payment QR renderer [{$driver}].");
        }

        $renderer = $this->container->make($class);

        if (! $renderer instanceof VoucherPaymentQrRendererContract) {
            throw new RuntimeException("Payment QR renderer [{$driver}] must implement VoucherPaymentQrRendererContract.");
        }

        return $renderer;
    }
}
