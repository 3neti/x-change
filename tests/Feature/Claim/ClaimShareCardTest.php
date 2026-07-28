<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;

beforeEach(function (): void {
    Cache::clear();
    Http::preventStrayRequests();
    config()->set('app.url', 'https://share.example.test');
    URL::forceRootUrl('https://share.example.test');
    URL::forceScheme('https');
});

it('renders and conditionally caches a deterministic Rider Stamp PNG', function (): void {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'rider' => [
            'message' => 'Snacks for today',
            'stamp' => [
                'version' => 2,
                'source' => 'message',
                'artwork_source' => 'x_change',
                'copy_source' => 'message',
                'title' => 'A little something',
                'description' => 'Open this Pay Code when you are ready.',
                'show_logo' => true,
                'show_tagline' => true,
                'claim_marker' => 'both',
                'claim_marker_position' => 'bottom_right',
            ],
        ],
    ]));

    $url = route('x-change.claim.share-card', ['code' => $voucher->code]);
    $response = $this->get($url)
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    $contents = (string) $response->getContent();
    $dimensions = getimagesizefromstring($contents);
    $etag = (string) $response->headers->get('ETag');

    expect($contents)
        ->toStartWith("\x89PNG\r\n\x1a\n")
        ->and($dimensions)->not->toBeFalse()
        ->and($dimensions[0])->toBe(1200)
        ->and($dimensions[1])->toBe(630)
        ->and($etag)->toMatch('/^"[a-f0-9]{64}"$/')
        ->and((string) $response->headers->get('Cache-Control'))
        ->toContain('public');

    $this->withHeader('If-None-Match', $etag)
        ->get($url)
        ->assertStatus(304)
        ->assertHeader('ETag', $etag);

    Http::assertNothingSent();
});

it('paints the masked recipient in the lower Stamp region', function (): void {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'feedback' => [
            'mobile' => '+639467438575',
        ],
        'rider' => [
            'stamp' => [
                'version' => 2,
                'artwork_source' => 'x_change',
                'copy_source' => 'none',
                'show_logo' => false,
                'show_tagline' => false,
                'claim_marker' => 'none',
            ],
        ],
    ]));

    $response = $this->get(
        route('x-change.claim.share-card', ['code' => $voucher->code]),
    )->assertOk();
    $card = imagecreatefromstring((string) $response->getContent());

    expect($card)->toBeInstanceOf(GdImage::class);

    $lightPixels = 0;

    for ($y = 495; $y <= 565; $y += 2) {
        for ($x = 60; $x <= 430; $x += 2) {
            $color = imagecolorsforindex($card, imagecolorat($card, $x, $y));

            if (
                $color['red'] > 180
                && $color['green'] > 180
                && $color['blue'] > 180
            ) {
                $lightPixels++;
            }
        }
    }

    imagedestroy($card);

    expect($lightPixels)->toBeGreaterThan(100);
    Http::assertNothingSent();
});

it('uses allow-listed URL artwork without exposing the raw artwork as og image', function (): void {
    $artwork = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl2nK0AAAAASUVORK5CYII=',
        true,
    );

    Http::fake([
        'https://open.spotify.com/track/6CKoWCWAqEVWVjpeoJXyNH' => Http::response(
            <<<'HTML'
            <!doctype html>
            <html>
                <head>
                    <meta property="og:title" content="An Example Track">
                    <meta property="og:description" content="An Example Artist">
                    <meta property="og:image" content="https://i.scdn.co/image/example-artwork">
                </head>
            </html>
            HTML,
            200,
            ['Content-Type' => 'text/html; charset=utf-8'],
        ),
        'https://i.scdn.co/image/example-artwork' => Http::response(
            $artwork,
            200,
            ['Content-Type' => 'image/png'],
        ),
    ]);

    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'rider' => [
            'message' => 'Snacks',
            'url' => 'https://open.spotify.com/track/6CKoWCWAqEVWVjpeoJXyNH?si=tracking-token',
            'splash' => '<img src="https://untrusted.example.test/splash.png">',
            'stamp' => [
                'version' => 2,
                'source' => 'url',
                'artwork_source' => 'url',
                'copy_source' => 'message',
            ],
        ],
    ]));

    $this->get(route('x-change.claim.share-card', ['code' => $voucher->code]))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');

    Http::assertSentCount(2);
    Http::assertNotSent(
        fn ($request): bool => $request->url() === 'https://untrusted.example.test/splash.png',
    );
});

it('falls back to x-change artwork when selected URL artwork is unavailable', function (): void {
    Http::fake([
        'https://open.spotify.com/track/6CKoWCWAqEVWVjpeoJXyNH' => Http::response(
            'temporarily unavailable',
            503,
            ['Content-Type' => 'text/plain'],
        ),
    ]);

    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'rider' => [
            'message' => 'Snacks',
            'url' => 'https://open.spotify.com/track/6CKoWCWAqEVWVjpeoJXyNH',
            'splash' => '<img src="https://untrusted.example.test/splash.png">',
            'stamp' => [
                'version' => 2,
                'source' => 'url',
                'artwork_source' => 'url',
                'copy_source' => 'message',
            ],
        ],
    ]));

    $response = $this->get(
        route('x-change.claim.share-card', ['code' => $voucher->code]),
    )->assertOk();

    expect((string) $response->getContent())->toStartWith("\x89PNG\r\n\x1a\n");
    Http::assertSentCount(1);
    Http::assertNotSent(
        fn ($request): bool => $request->url() === 'https://untrusted.example.test/splash.png',
    );
});

it('uses Rider Splash artwork only when the Stamp explicitly selects it', function (): void {
    $splashImage = imagecreatetruecolor(8, 8);
    $red = imagecolorallocate($splashImage, 244, 63, 94);
    imagefilledrectangle($splashImage, 0, 0, 8, 8, $red);
    ob_start();
    imagepng($splashImage);
    $splashContents = ob_get_clean();
    imagedestroy($splashImage);

    expect($splashContents)->toBeString()->not->toBeEmpty();

    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'rider' => [
            'message' => 'A rose-colored introduction',
            'splash' => '<img src="data:image/png;base64,'.base64_encode($splashContents).'">',
            'stamp' => [
                'version' => 2,
                'source' => 'splash',
                'artwork_source' => 'splash',
                'copy_source' => 'message',
                'scrim' => 0,
            ],
        ],
    ]));

    $response = $this->get(
        route('x-change.claim.share-card', ['code' => $voucher->code]),
    )->assertOk();
    $card = imagecreatefromstring((string) $response->getContent());

    expect($card)->toBeInstanceOf(GdImage::class);

    $center = imagecolorsforindex($card, imagecolorat($card, 600, 315));
    imagedestroy($card);

    expect($center['red'])
        ->toBeGreaterThan($center['green'])
        ->and($center['red'])->toBeGreaterThan($center['blue']);

    Http::assertNothingSent();
});

it('does not expose a share card for an unknown Pay Code', function (): void {
    $this->get(route('x-change.claim.share-card', ['code' => 'MISSING']))
        ->assertNotFound();
});
