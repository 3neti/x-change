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

it('falls back to Open Graph when oEmbed responds with the wrong content type', function () {
    actingAsTestUser();

    Http::fake([
        'https://open.spotify.com/oembed*' => Http::response(
            '{"title":"This must not be trusted as JSON"}',
            200,
            ['Content-Type' => 'text/html'],
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
            ['Content-Type' => 'text/html; charset=UTF-8'],
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

it('rejects oversized Open Graph documents before parsing metadata', function () {
    actingAsTestUser();
    config()->set(
        'x-change.cockpit.quick_generate.url_artwork.maximum_document_bytes',
        1024,
    );

    Http::fake([
        'https://open.spotify.com/oembed*' => Http::response(
            ['error' => 'temporarily unavailable'],
            503,
            ['Content-Type' => 'application/json'],
        ),
        'https://open.spotify.com/track/6CKoWCWAqEVWVjpeoJXyNH' => Http::response(
            str_repeat('x', 1025),
            200,
            ['Content-Type' => 'text/html'],
        ),
    ]);

    $this->postJson(
        route('x-change.cockpit.quick-generate.artwork-previews.store'),
        ['url' => 'https://open.spotify.com/track/6CKoWCWAqEVWVjpeoJXyNH'],
    )
        ->assertOk()
        ->assertJsonPath('available', false)
        ->assertJsonPath('image_url', null);

    Http::assertSentCount(2);
});

it('rejects oversized and unsupported artwork payloads', function (string $mimeType, string $body) {
    actingAsTestUser();
    config()->set(
        'x-change.cockpit.quick_generate.url_artwork.maximum_image_bytes',
        1024,
    );

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
            $body,
            200,
            ['Content-Type' => $mimeType],
        ),
    ]);

    $this->postJson(
        route('x-change.cockpit.quick-generate.artwork-previews.store'),
        ['url' => 'https://open.spotify.com/track/6CKoWCWAqEVWVjpeoJXyNH'],
    )
        ->assertOk()
        ->assertJsonPath('available', false)
        ->assertJsonPath('image_url', null);

    Http::assertSentCount(2);
})->with([
    'oversized JPEG' => ['image/jpeg', str_repeat('x', 1025)],
    'SVG' => ['image/svg+xml', '<svg xmlns="http://www.w3.org/2000/svg"/>'],
    'GIF' => ['image/gif', 'GIF89a'],
    'empty JPEG' => ['image/jpeg', ''],
]);

it('does not follow metadata or artwork redirects', function (string $redirectingUrl) {
    actingAsTestUser();

    Http::fake(function ($request) use ($redirectingUrl) {
        if (str_starts_with($request->url(), 'https://open.spotify.com/oembed')) {
            return $redirectingUrl === 'metadata'
                ? Http::response('', 302, ['Location' => 'https://example.com/metadata'])
                : Http::response([
                    'title' => 'An Example Track',
                    'provider_name' => 'Spotify',
                    'thumbnail_url' => 'https://i.scdn.co/image/example-artwork',
                ], 200, ['Content-Type' => 'application/json']);
        }

        if ($request->url() === 'https://open.spotify.com/track/6CKoWCWAqEVWVjpeoJXyNH') {
            return Http::response('', 302, ['Location' => 'https://example.com/page']);
        }

        return Http::response('', 302, ['Location' => 'https://example.com/image']);
    });

    $this->postJson(
        route('x-change.cockpit.quick-generate.artwork-previews.store'),
        ['url' => 'https://open.spotify.com/track/6CKoWCWAqEVWVjpeoJXyNH'],
    )
        ->assertOk()
        ->assertJsonPath('available', false)
        ->assertJsonPath('image_url', null);

    Http::assertNotSent(
        fn ($request): bool => str_starts_with($request->url(), 'https://example.com/'),
    );
})->with(['metadata', 'artwork']);

it('keeps YouTube artwork disabled by default', function () {
    actingAsTestUser();

    $this->postJson(
        route('x-change.cockpit.quick-generate.artwork-previews.store'),
        ['url' => 'https://youtu.be/Hz_wdBH0fTo?si=tracking'],
    )
        ->assertOk()
        ->assertJsonPath('available', false)
        ->assertJsonPath('source', 'link');

    Http::assertNothingSent();
});

it('resolves supported YouTube URL variants through one canonical oEmbed URL', function (string $url) {
    actingAsTestUser();
    config()->set(
        'x-change.cockpit.quick_generate.url_artwork.enabled_drivers.youtube',
        true,
    );

    Http::fake([
        'https://www.youtube.com/oembed*' => Http::response(
            [
                'title' => 'The Killers - I Want to Hold Your Hand (The Beatles Cover) at Forest Hills Stadium in NY',
                'author_name' => 'tkillers music',
                'provider_name' => 'YouTube',
                'thumbnail_url' => 'https://i.ytimg.com/vi/Hz_wdBH0fTo/hqdefault.jpg',
                'html' => '<iframe src="https://www.youtube.com/embed/Hz_wdBH0fTo"></iframe>',
            ],
            200,
            ['Content-Type' => 'application/json'],
        ),
        'https://i.ytimg.com/vi/Hz_wdBH0fTo/hqdefault.jpg' => Http::response(
            'youtube-jpeg-bytes',
            200,
            ['Content-Type' => 'image/jpeg'],
        ),
    ]);

    $this->postJson(
        route('x-change.cockpit.quick-generate.artwork-previews.store'),
        ['url' => $url],
    )
        ->assertOk()
        ->assertJsonPath('schema', 'x-change.cockpit.rider-artwork-preview.v1')
        ->assertJsonPath('available', true)
        ->assertJsonPath('source', 'youtube')
        ->assertJsonPath(
            'title',
            'The Killers - I Want to Hold Your Hand (The Beatles Cover) at Forest Hills Stadium in NY',
        )
        ->assertJsonPath('description', 'YouTube')
        ->assertJsonPath('reference', 'YouTube')
        ->assertJsonPath(
            'image_url',
            'data:image/jpeg;base64,'.base64_encode('youtube-jpeg-bytes'),
        )
        ->assertJsonPath(
            'public_image_url',
            'https://i.ytimg.com/vi/Hz_wdBH0fTo/hqdefault.jpg',
        )
        ->assertJsonMissingPath('html')
        ->assertJsonMissingPath('author_name');

    Http::assertSentCount(2);
    Http::assertSent(
        fn ($request): bool => $request->url()
            === 'https://www.youtube.com/oembed?url='.
                urlencode('https://www.youtube.com/watch?v=Hz_wdBH0fTo'),
    );
})->with([
    'short URL' => 'https://youtu.be/Hz_wdBH0fTo',
    'short URL with tracking' => 'https://youtu.be/Hz_wdBH0fTo?si=KnyudYHL2qXGXtb5',
    'www watch URL' => 'https://www.youtube.com/watch?v=Hz_wdBH0fTo',
    'watch URL with tracking' => 'https://youtube.com/watch?v=Hz_wdBH0fTo&si=tracking',
    'mobile watch URL' => 'https://m.youtube.com/watch?v=Hz_wdBH0fTo',
    'Shorts URL' => 'https://www.youtube.com/shorts/Hz_wdBH0fTo?feature=share',
    'embed URL' => 'https://www.youtube.com/embed/Hz_wdBH0fTo',
    'live URL' => 'https://www.youtube.com/live/Hz_wdBH0fTo?si=tracking',
]);

it('shares the YouTube cache entry across URL variants and tracking tokens', function () {
    config()->set(
        'x-change.cockpit.quick_generate.url_artwork.enabled_drivers.youtube',
        true,
    );

    Http::fake([
        'https://www.youtube.com/oembed*' => Http::response(
            [
                'title' => 'An Example Video',
                'provider_name' => 'YouTube',
                'thumbnail_url' => 'https://i.ytimg.com/vi/Hz_wdBH0fTo/hqdefault.jpg',
            ],
            200,
            ['Content-Type' => 'application/json'],
        ),
        'https://i.ytimg.com/vi/Hz_wdBH0fTo/hqdefault.jpg' => Http::response(
            'youtube-jpeg-bytes',
            200,
            ['Content-Type' => 'image/jpeg'],
        ),
    ]);

    $resolver = app(RiderUrlArtworkPreviewResolver::class);
    $first = $resolver->resolve('https://youtu.be/Hz_wdBH0fTo?si=first');
    $second = $resolver->resolve(
        'https://www.youtube.com/watch?v=Hz_wdBH0fTo&si=second',
    );

    expect($first)->toBe($second)
        ->and($first['available'])->toBeTrue();
    Http::assertSentCount(2);
});

it('rejects malformed or unsafe YouTube resources before making HTTP requests', function (string $url) {
    actingAsTestUser();
    config()->set(
        'x-change.cockpit.quick_generate.url_artwork.enabled_drivers.youtube',
        true,
    );

    $this->postJson(
        route('x-change.cockpit.quick-generate.artwork-previews.store'),
        ['url' => $url],
    )
        ->assertOk()
        ->assertJsonPath('available', false)
        ->assertJsonPath('source', 'link');

    Http::assertNothingSent();
})->with([
    'missing watch id' => 'https://www.youtube.com/watch',
    'short id' => 'https://youtu.be/Hz_wdBH0fT',
    'long id' => 'https://youtu.be/Hz_wdBH0fToo',
    'invalid id character' => 'https://youtu.be/Hz_wdBH0fT!',
    'duplicate watch id' => 'https://www.youtube.com/watch?v=Hz_wdBH0fTo&v=dQw4w9WgXcQ',
    'playlist only' => 'https://www.youtube.com/playlist?list=PL123',
    'channel' => 'https://www.youtube.com/@tkillersmusic',
    'spoofed host' => 'https://youtube.com.evil.example/watch?v=Hz_wdBH0fTo',
    'different host' => 'https://notyoutube.com/watch?v=Hz_wdBH0fTo',
    'userinfo' => 'https://user:secret@www.youtube.com/watch?v=Hz_wdBH0fTo',
    'non-standard port' => 'https://www.youtube.com:8443/watch?v=Hz_wdBH0fTo',
    'IP address' => 'https://127.0.0.1/watch?v=Hz_wdBH0fTo',
    'localhost' => 'https://localhost/watch?v=Hz_wdBH0fTo',
    'extra short path' => 'https://youtu.be/Hz_wdBH0fTo/more',
]);

it('refuses YouTube thumbnails outside the driver allowlist', function () {
    actingAsTestUser();
    config()->set(
        'x-change.cockpit.quick_generate.url_artwork.enabled_drivers.youtube',
        true,
    );

    Http::fake([
        'https://www.youtube.com/oembed*' => Http::response(
            [
                'title' => 'Untrusted Video',
                'provider_name' => 'YouTube',
                'thumbnail_url' => 'https://i.ytimg.com.evil.example/private.jpg',
            ],
            200,
            ['Content-Type' => 'application/json'],
        ),
        'https://www.youtube.com/watch*' => Http::response(
            'unavailable',
            503,
            ['Content-Type' => 'text/plain'],
        ),
    ]);

    $this->postJson(
        route('x-change.cockpit.quick-generate.artwork-previews.store'),
        ['url' => 'https://youtu.be/Hz_wdBH0fTo'],
    )
        ->assertOk()
        ->assertJsonPath('available', false)
        ->assertJsonPath('image_url', null);

    Http::assertSentCount(1);
    Http::assertNotSent(
        fn ($request): bool => $request->url()
            === 'https://i.ytimg.com.evil.example/private.jpg',
    );
});

it('falls back to canonical YouTube Open Graph metadata when oEmbed is unavailable', function () {
    actingAsTestUser();
    config()->set(
        'x-change.cockpit.quick_generate.url_artwork.enabled_drivers.youtube',
        true,
    );

    Http::fake([
        'https://www.youtube.com/oembed*' => Http::response(
            ['error' => 'temporarily unavailable'],
            503,
            ['Content-Type' => 'application/json'],
        ),
        'https://www.youtube.com/watch?v=Hz_wdBH0fTo' => Http::response(
            <<<'HTML'
            <html>
                <head>
                    <meta property="og:title" content="Fallback Video">
                    <meta property="og:description" content="Fallback Channel">
                    <meta property="og:image" content="https://i.ytimg.com/vi/Hz_wdBH0fTo/hqdefault.jpg">
                </head>
            </html>
            HTML,
            200,
            ['Content-Type' => 'text/html'],
        ),
        'https://i.ytimg.com/vi/Hz_wdBH0fTo/hqdefault.jpg' => Http::response(
            'fallback-youtube-jpeg',
            200,
            ['Content-Type' => 'image/jpeg'],
        ),
    ]);

    $this->postJson(
        route('x-change.cockpit.quick-generate.artwork-previews.store'),
        ['url' => 'https://youtu.be/Hz_wdBH0fTo?si=tracking'],
    )
        ->assertOk()
        ->assertJsonPath('available', true)
        ->assertJsonPath('source', 'youtube')
        ->assertJsonPath('title', 'Fallback Video')
        ->assertJsonPath('description', 'Fallback Channel');

    Http::assertSentCount(3);
});

it('may use the legacy Spotify configuration as a rollback source', function () {
    config()->set(
        'x-change.cockpit.quick_generate.url_artwork.driver_source',
        'legacy',
    );

    Http::fake([
        'https://open.spotify.com/oembed*' => Http::response(
            [
                'title' => 'Legacy Configuration Track',
                'provider_name' => 'Spotify',
                'thumbnail_url' => 'https://i.scdn.co/image/legacy-artwork',
            ],
            200,
            ['Content-Type' => 'application/json'],
        ),
        'https://i.scdn.co/image/legacy-artwork' => Http::response(
            'legacy-jpeg-bytes',
            200,
            ['Content-Type' => 'image/jpeg'],
        ),
    ]);

    $resolved = app(RiderUrlArtworkPreviewResolver::class)->resolve(
        'https://open.spotify.com/track/6CKoWCWAqEVWVjpeoJXyNH?si=tracking',
    );

    expect($resolved['available'])->toBeTrue()
        ->and($resolved['source'])->toBe('spotify')
        ->and($resolved['title'])->toBe('Legacy Configuration Track');
    Http::assertSentCount(2);
});
