<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\XChange\Contracts\VoucherPaymentProviderContract;
use LBHurtado\XChange\Services\PaymentProviders\ManualVoucherPaymentProvider;
use LBHurtado\XChange\Services\PaymentProviders\NullVoucherPaymentProvider;
use RuntimeException;

class VoucherPaymentProviderManager
{
    public function driver(?string $name = null): VoucherPaymentProviderContract
    {
        $name = $name ?: (string) config('x-change.payment.default_provider', 'manual');

        return match ($name) {
            'manual' => $this->manual(),
            'null' => app(NullVoucherPaymentProvider::class),
            default => $this->custom($name),
        };
    }

    protected function manual(): VoucherPaymentProviderContract
    {
        if (
            ! $this->allowsManualProviderByEnvironment()
            && ! (bool) config('x-change.payment.allow_manual_provider_in_production', false)
        ) {
            throw new RuntimeException('Manual voucher payment provider is disabled outside local/testing environments.');
        }

        return app(ManualVoucherPaymentProvider::class);
    }

    protected function allowsManualProviderByEnvironment(): bool
    {
        return app()->environment(['local', 'testing'])
            && in_array((string) config('app.env'), ['local', 'testing'], true);
    }

    protected function custom(string $name): VoucherPaymentProviderContract
    {
        $drivers = (array) config('x-change.payment.providers', []);
        $class = $drivers[$name] ?? null;

        if (! is_string($class) || $class === '' || ! class_exists($class)) {
            throw new RuntimeException("Unsupported voucher payment provider [{$name}].");
        }

        $provider = app($class);

        if (! $provider instanceof VoucherPaymentProviderContract) {
            throw new RuntimeException("Voucher payment provider [{$name}] must implement VoucherPaymentProviderContract.");
        }

        return $provider;
    }
}
