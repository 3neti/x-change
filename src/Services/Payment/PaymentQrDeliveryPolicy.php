<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Payment;

use Illuminate\Validation\ValidationException;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Enums\PaymentQrDeliveryMode;
use LBHurtado\XChange\Models\PaymentAttempt;

final class PaymentQrDeliveryPolicy
{
    /** @return list<PaymentQrDeliveryMode> */
    public function forVoucher(Voucher $voucher, bool $pairedDisplay): array
    {
        $configured = data_get(
            $voucher->metadata,
            'instructions.metadata.custom.payment.qr_delivery_modes',
        );

        return $configured === null
            ? [$pairedDisplay ? PaymentQrDeliveryMode::SellerDisplay : PaymentQrDeliveryMode::PayerPage]
            : $this->validate($configured);
    }

    /** @return list<PaymentQrDeliveryMode>|null */
    public function validateInstructions(array $instructions): ?array
    {
        $configured = data_get($instructions, 'metadata.custom.payment.qr_delivery_modes');

        return $configured === null ? null : $this->validate($configured);
    }

    /**
     * @param  list<PaymentQrDeliveryMode>  $modes
     * @return list<string>
     */
    public function values(array $modes): array
    {
        return array_map(
            static fn (PaymentQrDeliveryMode $mode): string => $mode->value,
            $modes,
        );
    }

    /** @return list<PaymentQrDeliveryMode> */
    public function forAttempt(
        PaymentAttempt $attempt,
        Voucher $voucher,
        bool $pairedDisplay,
    ): array {
        $snapshot = data_get($attempt->metadata, 'qr_delivery_modes');

        return $snapshot === null
            ? $this->forVoucher($voucher, $pairedDisplay)
            : $this->validate($snapshot);
    }

    public function allows(
        PaymentAttempt $attempt,
        Voucher $voucher,
        bool $pairedDisplay,
        PaymentQrDeliveryMode $mode,
    ): bool {
        return in_array($mode, $this->forAttempt($attempt, $voucher, $pairedDisplay), true);
    }

    /** @return list<PaymentQrDeliveryMode> */
    private function validate(mixed $configured): array
    {
        if (! is_array($configured) || $configured === []) {
            throw ValidationException::withMessages([
                'payment.qr_delivery_modes' => 'Choose at least one supported QR delivery mode.',
            ]);
        }

        $modes = [];

        foreach ($configured as $value) {
            $mode = is_string($value) ? PaymentQrDeliveryMode::tryFrom($value) : null;

            if ($mode === null) {
                throw ValidationException::withMessages([
                    'payment.qr_delivery_modes' => 'The Pay Code template requests an unsupported QR delivery mode.',
                ]);
            }

            $modes[$mode->value] = $mode;
        }

        $modes = array_values($modes);
        $sellerDisplay = in_array(PaymentQrDeliveryMode::SellerDisplay, $modes, true);
        $payerPage = in_array(PaymentQrDeliveryMode::PayerPage, $modes, true);
        $downloadable = in_array(PaymentQrDeliveryMode::Downloadable, $modes, true);
        $apiPayload = in_array(PaymentQrDeliveryMode::ApiPayload, $modes, true);

        if ($sellerDisplay && count($modes) > 1) {
            throw ValidationException::withMessages([
                'payment.qr_delivery_modes' => 'Seller-display QR delivery cannot be combined with another delivery mode.',
            ]);
        }

        if ($downloadable && ! $payerPage) {
            throw ValidationException::withMessages([
                'payment.qr_delivery_modes' => 'Downloadable QR delivery requires payer-page delivery.',
            ]);
        }

        if ($apiPayload && count($modes) > 1) {
            throw ValidationException::withMessages([
                'payment.qr_delivery_modes' => 'API-payload QR delivery cannot be combined with a browser delivery mode.',
            ]);
        }

        return $modes;
    }
}
