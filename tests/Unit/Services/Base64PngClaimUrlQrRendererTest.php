<?php

declare(strict_types=1);

use LBHurtado\XChange\Services\Base64PngClaimUrlQrRenderer;
use Zxing\QrReader;

it('renders a canonical claim URL as a base64 PNG data URI', function (): void {
    $rendered = (new Base64PngClaimUrlQrRenderer)
        ->render('https://example.test/x/claim/PAY-QR-1');

    expect($rendered)
        ->toStartWith('data:image/png;base64,')
        ->and(base64_decode(str_replace('data:image/png;base64,', '', $rendered), true))
        ->toStartWith("\x89PNG");

    $png = base64_decode(str_replace('data:image/png;base64,', '', $rendered), true);
    $decoded = new QrReader($png, QrReader::SOURCE_TYPE_BLOB, false);

    expect($decoded->text())->toBe('https://example.test/x/claim/PAY-QR-1');
});

it('rejects non-HTTP claim destinations', function (string $claimUrl): void {
    expect(fn () => (new Base64PngClaimUrlQrRenderer)->render($claimUrl))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'relative path' => '/x/claim/PAY-QR-1',
    'script URL' => 'javascript:alert(1)',
]);

it('renders a natively dense QR with the same canonical destination', function (): void {
    $renderer = new Base64PngClaimUrlQrRenderer;
    $url = 'https://example.test/x/claim/NATIVE-HD';
    $png = base64_decode(explode(',', $renderer->renderAtScale($url, 2), 2)[1]);
    expect(getimagesizefromstring($png)[0])->toBe(512);
    expect((new QrReader($png, QrReader::SOURCE_TYPE_BLOB, false))->text())->toBe($url);
    expect(fn () => $renderer->renderAtScale($url, 3))->toThrow(InvalidArgumentException::class);
});
