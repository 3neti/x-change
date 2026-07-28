<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use LBHurtado\XChange\Contracts\RiderStampCopyResolverContract;

it('matches the canvas automatic copy priority by choosing Rider Message first', function (): void {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'rider' => [
            'message' => 'Snacks',
            'url' => 'https://open.spotify.com/track/example',
            'splash' => '<h2>A separate introduction</h2><p>Shown before claim.</p>',
            'stamp' => [
                'version' => 2,
                'source' => 'url',
                'copy_source' => 'automatic',
            ],
        ],
    ]));

    $copy = app(RiderStampCopyResolverContract::class)->resolve($voucher);

    expect($copy->source)->toBe('message')
        ->and($copy->visible)->toBeTrue()
        ->and($copy->title)->toBe('Snacks')
        ->and($copy->description)->toBe('');
});

it('honors each explicit Rider Stamp copy source', function (
    string $source,
    string $expectedTitle,
    string $expectedDescription,
): void {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'rider' => [
            'message' => 'Snacks',
            'url' => 'https://example.test/after-claim',
            'splash' => '<h2>A separate introduction</h2><p>Shown before claim.</p>',
            'stamp' => [
                'version' => 2,
                'source' => 'url',
                'copy_source' => $source,
            ],
        ],
    ]));

    $copy = app(RiderStampCopyResolverContract::class)->resolve($voucher);

    expect($copy->source)->toBe($source)
        ->and($copy->visible)->toBe($source !== 'none')
        ->and($copy->title)->toBe($expectedTitle)
        ->and($copy->description)->toBe($expectedDescription);
})->with([
    'message' => [
        'message',
        'Snacks',
        '',
    ],
    'url' => [
        'url',
        'https://example.test/after-claim',
        'Continue to this link after the claim.',
    ],
    'splash' => [
        'splash',
        'A separate introduction',
        'Shown before claim.',
    ],
    'none' => [
        'none',
        'Pay Code',
        'A Pay Code is ready to claim securely in X-Change.',
    ],
]);

it('uses custom Stamp title and subtitle as copy overrides', function (): void {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'rider' => [
            'message' => 'Snacks',
            'stamp' => [
                'version' => 2,
                'source' => 'message',
                'copy_source' => 'custom',
                'title' => 'For tonight',
                'description' => 'A custom note on the Pay Code front.',
            ],
        ],
    ]));

    $copy = app(RiderStampCopyResolverContract::class)->resolve($voucher);

    expect($copy->source)->toBe('custom')
        ->and($copy->visible)->toBeTrue()
        ->and($copy->title)->toBe('For tonight')
        ->and($copy->description)->toBe(
            'A custom note on the Pay Code front.',
        );
});

it('preserves every visible Rider Splash paragraph as safe Stamp copy', function (): void {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'rider' => [
            'splash' => <<<'HTML'
                <div>
                    <h2>i carry your heart with me</h2>
                    <p>(i carry it in my heart)</p>
                    <p>🤝 &nbsp; ❤️ &nbsp; ✌️ &nbsp; 🔫 &nbsp; ✈️ &nbsp; ⭐</p>
                    <p>&mdash; e.e. cummings</p>
                </div>
                HTML,
            'stamp' => [
                'version' => 2,
                'source' => 'splash',
                'copy_source' => 'splash',
            ],
        ],
    ]));

    $copy = app(RiderStampCopyResolverContract::class)->resolve($voucher);

    expect($copy->title)->toBe('i carry your heart with me')
        ->and($copy->description)->toBe(
            '(i carry it in my heart) · 🤝 ❤️ ✌️ 🔫 ✈️ ⭐ · — e.e. cummings',
        )
        ->and($copy->rasterDescription)->toBe(
            '(i carry it in my heart) · Handshake Heart Peace Water pistol Flight Star · — e.e. cummings',
        );
});

it('uses allow-listed Rider URL metadata when it is available', function (): void {
    Cache::clear();
    Http::preventStrayRequests();
    Http::fake([
        'https://open.spotify.com/track/6CKoWCWAqEVWVjpeoJXyNH' => Http::response(
            <<<'HTML'
            <meta property="og:title" content="An Example Track">
            <meta property="og:description" content="An Example Artist">
            <meta property="og:image" content="https://i.scdn.co/image/example-artwork">
            HTML,
            200,
            ['Content-Type' => 'text/html'],
        ),
        'https://i.scdn.co/image/example-artwork' => Http::response(
            base64_decode(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl2nK0AAAAASUVORK5CYII=',
                true,
            ),
            200,
            ['Content-Type' => 'image/png'],
        ),
    ]);

    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'rider' => [
            'url' => 'https://open.spotify.com/track/6CKoWCWAqEVWVjpeoJXyNH?si=tracking-token',
            'stamp' => [
                'version' => 2,
                'source' => 'url',
                'copy_source' => 'url',
            ],
        ],
    ]));

    $copy = app(RiderStampCopyResolverContract::class)->resolve($voucher);

    expect($copy->title)->toBe('An Example Track')
        ->and($copy->description)->toBe('An Example Artist');

    Http::assertSentCount(2);
});

it('keeps Rider Message copy title-only when no custom subtitle is supplied', function (): void {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'rider' => [
            'message' => 'Snacks',
            'stamp' => [
                'version' => 2,
                'source' => 'message',
                'copy_source' => 'message',
            ],
        ],
    ]));

    $copy = app(RiderStampCopyResolverContract::class)->resolve($voucher);

    expect($copy->title)->toBe('Snacks')
        ->and($copy->description)->toBe('')
        ->and($copy->rasterDescription)->toBe('');
});
