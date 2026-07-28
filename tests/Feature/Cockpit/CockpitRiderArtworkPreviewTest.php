<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use LBHurtado\XChange\Services\Cockpit\RiderUrlArtworkPreviewResolver;

beforeEach(function (): void {
    Cache::clear();
    Http::preventStrayRequests();
});

it('resolves and caches sanitized Spotify action link artwork', function () {
    actingAsTestUser();

    Http::fake([
        'https://open.spotify.com/oembed*' => Http::response(
            [
                'title' => 'An Example Track',
                'provider_name' => 'Spotify',
                'thumbnail_url' => 'https://i.scdn.co/image/example-artwork',
            ],
            200,
            ['Content-Type' => 'application/json'],
        ),
        'https://i.scdn.co/image/example-artwork' => Http::response(
            'fake-jpeg-bytes',
            200,
            ['Content-Type' => 'image/jpeg'],
        ),
    ]);

    $url = 'https://open.spotify.com/track/6CKoWCWAqEVWVjpeoJXyNH?si=tracking-token';

    foreach (range(1, 2) as $attempt) {
        $this->postJson(
            route('x-change.cockpit.quick-generate.artwork-previews.store'),
            ['url' => $url],
        )
            ->assertOk()
            ->assertJsonPath('schema', 'x-change.cockpit.rider-artwork-preview.v1')
            ->assertJsonPath('available', true)
            ->assertJsonPath('source', 'spotify')
            ->assertJsonPath('title', 'An Example Track')
            ->assertJsonPath('description', 'Spotify')
            ->assertJsonPath(
                'image_url',
                'data:image/jpeg;base64,'.base64_encode('fake-jpeg-bytes'),
            )
            ->assertJsonPath(
                'public_image_url',
                'https://i.scdn.co/image/example-artwork',
            )
            ->assertJsonMissingPath('html')
            ->assertHeader('Cache-Control', 'must-revalidate, no-cache, no-store, private');
    }

    Http::assertSentCount(2);
    Http::assertSent(
        fn ($request): bool => $request->url()
            === 'https://open.spotify.com/oembed?url='
                .urlencode('https://open.spotify.com/track/6CKoWCWAqEVWVjpeoJXyNH'),
    );
    Http::assertSent(
        fn ($request): bool => $request->url()
            === 'https://i.scdn.co/image/example-artwork',
    );
});

it('keeps unsupported action links on the safe text fallback', function () {
    actingAsTestUser();

    $this->postJson(
        route('x-change.cockpit.quick-generate.artwork-previews.store'),
        ['url' => 'https://example.com/campaign'],
    )
        ->assertOk()
        ->assertJsonPath('available', false)
        ->assertJsonPath('image_url', null)
        ->assertJsonPath('public_image_url', null);

    Http::assertNothingSent();
});

it('refuses artwork downloads outside approved Spotify image hosts', function () {
    actingAsTestUser();

    Http::fake([
        'https://open.spotify.com/oembed*' => Http::response(
            [
                'title' => 'Untrusted Artwork',
                'provider_name' => 'Spotify',
                'thumbnail_url' => 'https://internal.example.test/private-image',
            ],
            200,
            ['Content-Type' => 'application/json'],
        ),
    ]);

    $this->postJson(
        route('x-change.cockpit.quick-generate.artwork-previews.store'),
        ['url' => 'https://open.spotify.com/track/6CKoWCWAqEVWVjpeoJXyNH'],
    )
        ->assertOk()
        ->assertJsonPath('available', false)
        ->assertJsonPath('image_url', null);

    Http::assertSentCount(1);
});

it('falls back to Open Graph metadata when provider metadata is unavailable', function () {
    actingAsTestUser();

    Http::fake([
        'https://open.spotify.com/oembed*' => Http::response(
            ['error' => 'temporarily unavailable'],
            503,
            ['Content-Type' => 'application/json'],
        ),
        'https://open.spotify.com/track/6CKoWCWAqEVWVjpeoJXyNH' => Http::response(
            <<<'HTML'
            <html>
                <head>
                    <meta property="og:title" content="Fallback Track">
                    <meta property="og:description" content="Fallback Artist">
                    <meta property="og:image" content="https://i.scdn.co/image/fallback-artwork">
                </head>
            </html>
            HTML,
            200,
            ['Content-Type' => 'text/html'],
        ),
        'https://i.scdn.co/image/fallback-artwork' => Http::response(
            'fallback-jpeg-bytes',
            200,
            ['Content-Type' => 'image/jpeg'],
        ),
    ]);

    $this->postJson(
        route('x-change.cockpit.quick-generate.artwork-previews.store'),
        ['url' => 'https://open.spotify.com/track/6CKoWCWAqEVWVjpeoJXyNH'],
    )
        ->assertOk()
        ->assertJsonPath('available', true)
        ->assertJsonPath('title', 'Fallback Track')
        ->assertJsonPath('description', 'Fallback Artist');

    Http::assertSentCount(3);
});

it('briefly caches unavailable provider artwork so transient failures recover', function () {
    Cache::spy();
    config()->set(
        'x-change.cockpit.quick_generate.url_artwork.cache_ttl_seconds',
        3600,
    );

    Http::fake([
        'https://open.spotify.com/oembed*' => Http::response(
            ['error' => 'temporarily unavailable'],
            503,
            ['Content-Type' => 'application/json'],
        ),
        'https://open.spotify.com/track/6CKoWCWAqEVWVjpeoJXyNH' => Http::response(
            'unavailable',
            503,
            ['Content-Type' => 'text/plain'],
        ),
    ]);

    $resolved = app(RiderUrlArtworkPreviewResolver::class)->resolve(
        'https://open.spotify.com/track/6CKoWCWAqEVWVjpeoJXyNH',
    );
    $cacheKey = 'x-change:cockpit:rider-url-artwork:v2:'.hash(
        'sha256',
        'spotify|https://open.spotify.com/track/6CKoWCWAqEVWVjpeoJXyNH',
    );

    expect($resolved['available'])->toBeFalse();
    Cache::shouldHaveReceived('put')->once()->with(
        $cacheKey,
        Mockery::on(
            fn (mixed $value): bool => is_array($value)
                && ($value['available'] ?? null) === false,
        ),
        60,
    );
});

it('rejects non-https action links before resolution', function () {
    actingAsTestUser();

    $this->postJson(
        route('x-change.cockpit.quick-generate.artwork-previews.store'),
        ['url' => 'http://open.spotify.com/track/6CKoWCWAqEVWVjpeoJXyNH'],
    )->assertUnprocessable()->assertInvalid('url');

    Http::assertNothingSent();
});
