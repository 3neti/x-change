<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Cache::clear();
    Http::preventStrayRequests();
});

it('resolves and caches sanitized Spotify action link artwork', function () {
    actingAsTestUser();

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
            ->assertJsonPath('description', 'An Example Artist')
            ->assertJsonPath(
                'image_url',
                'data:image/jpeg;base64,'.base64_encode('fake-jpeg-bytes'),
            )
            ->assertJsonMissingPath('html')
            ->assertHeader('Cache-Control', 'must-revalidate, no-cache, no-store, private');
    }

    Http::assertSentCount(2);
    Http::assertSent(
        fn ($request): bool => $request->url()
            === 'https://open.spotify.com/track/6CKoWCWAqEVWVjpeoJXyNH',
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
        ->assertJsonPath('image_url', null);

    Http::assertNothingSent();
});

it('refuses artwork downloads outside approved Spotify image hosts', function () {
    actingAsTestUser();

    Http::fake([
        'https://open.spotify.com/track/6CKoWCWAqEVWVjpeoJXyNH' => Http::response(
            <<<'HTML'
            <html>
                <head>
                    <meta property="og:title" content="Untrusted Artwork">
                    <meta property="og:image" content="https://internal.example.test/private-image">
                </head>
            </html>
            HTML,
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

    Http::assertSentCount(1);
});

it('rejects non-https action links before resolution', function () {
    actingAsTestUser();

    $this->postJson(
        route('x-change.cockpit.quick-generate.artwork-previews.store'),
        ['url' => 'http://open.spotify.com/track/6CKoWCWAqEVWVjpeoJXyNH'],
    )->assertUnprocessable()->assertInvalid('url');

    Http::assertNothingSent();
});
