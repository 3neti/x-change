<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use LBHurtado\XChange\Enums\PaymentQrDeliveryMode;
use LBHurtado\XChange\Services\Payment\PaymentQrDeliveryPolicy;

it('accepts the remote payer page and downloadable QR combination', function (): void {
    $modes = app(PaymentQrDeliveryPolicy::class)->validateInstructions([
        'metadata' => [
            'custom' => [
                'payment' => [
                    'qr_delivery_modes' => ['payer_page', 'downloadable'],
                ],
            ],
        ],
    ]);

    expect($modes)->toBe([
        PaymentQrDeliveryMode::PayerPage,
        PaymentQrDeliveryMode::Downloadable,
    ]);
});

it('rejects unsupported and contradictory QR delivery contracts', function (array $modes): void {
    expect(fn () => app(PaymentQrDeliveryPolicy::class)->validateInstructions([
        'metadata' => [
            'custom' => [
                'payment' => ['qr_delivery_modes' => $modes],
            ],
        ],
    ]))->toThrow(ValidationException::class);
})->with([
    'unsupported mode' => [['telepathy']],
    'download without payer page' => [['downloadable']],
    'seller and payer surfaces' => [['seller_display', 'payer_page']],
    'api and browser surfaces' => [['api_payload', 'payer_page']],
]);
