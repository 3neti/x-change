<?php

declare(strict_types=1);

use LBHurtado\XChange\Services\Base64PngClaimUrlQrRenderer;

it('renders a canonical claim URL as a base64 PNG data URI', function (): void {
    $rendered = (new Base64PngClaimUrlQrRenderer)
        ->render('https://example.test/x/claim/PAY-QR-1');

    expect($rendered)
        ->toStartWith('data:image/png;base64,')
        ->and(base64_decode(str_replace('data:image/png;base64,', '', $rendered), true))
        ->toStartWith("\x89PNG");
});

it('rejects non-HTTP claim destinations', function (string $claimUrl): void {
    expect(fn () => (new Base64PngClaimUrlQrRenderer)->render($claimUrl))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'relative path' => '/x/claim/PAY-QR-1',
    'script URL' => 'javascript:alert(1)',
]);
