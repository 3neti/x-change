<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Inertia\ServiceProvider as InertiaServiceProvider;

beforeEach(function (): void {
    app()->register(InertiaServiceProvider::class);
    Cache::clear();
    Http::preventStrayRequests();
    config()->set('app.url', 'https://share.example.test');
    URL::forceRootUrl('https://share.example.test');
    URL::forceScheme('https');
    $this->withoutVite();
});

it('server renders Rider Stamp share metadata with a composed image endpoint', function (): void {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'rider' => [
            'message' => 'Snacks for today',
            'url' => 'https://open.spotify.com/track/6CKoWCWAqEVWVjpeoJXyNH?si=tracking-token',
            'stamp' => [
                'version' => 2,
                'source' => 'url',
                'artwork_source' => 'url',
                'copy_source' => 'message',
                'title' => 'A little something',
                'description' => 'Open this Pay Code when you are ready.',
            ],
        ],
    ]));

    $claimUrl = "https://share.example.test/x/claim/{$voucher->code}";
    $shareCardUrl = "{$claimUrl}/share-card.png";

    $this->withHeader('User-Agent', 'facebookexternalhit/1.1')
        ->get(route('x-change.claim.show', ['code' => $voucher->code]))
        ->assertOk()
        ->assertSee(
            '<meta property="og:title" content="A little something">',
            false,
        )
        ->assertSee(
            '<meta property="og:description" content="Open this Pay Code when you are ready.">',
            false,
        )
        ->assertSee(
            '<meta property="og:image" content="'.$shareCardUrl.'">',
            false,
        )
        ->assertSee(
            '<meta property="og:image:secure_url" content="'.$shareCardUrl.'">',
            false,
        )
        ->assertSee('<meta property="og:image:type" content="image/png">', false)
        ->assertSee('<meta property="og:image:width" content="1200">', false)
        ->assertSee('<meta property="og:image:height" content="630">', false)
        ->assertSee(
            '<meta property="og:url" content="'.$claimUrl.'">',
            false,
        )
        ->assertSee('<link rel="canonical" href="'.$claimUrl.'">', false)
        ->assertSee(
            '<meta name="twitter:card" content="summary_large_image">',
            false,
        )
        ->assertDontSee('data:image', false);

    Http::assertNothingSent();
});

it('escapes share copy and falls back to the configured public image', function (): void {
    config()->set(
        'x-change.claim.share.default_image',
        '/vendor/x-change/images/logo-orange.png',
    );

    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'rider' => [
            'message' => '<script>alert("message")</script> Lunch & more',
            'splash' => '<img src="javascript:alert(1)" alt="unsafe">',
            'stamp' => [
                'version' => 2,
                'source' => 'splash',
                'artwork_source' => 'splash',
                'title' => 'Lunch & "more"',
                'description' => '<strong>Claim safely</strong> & enjoy.',
            ],
        ],
    ]));

    $this->get(route('x-change.claim.show', ['code' => $voucher->code]))
        ->assertOk()
        ->assertSee(
            '<meta property="og:title" content="Lunch &amp; &quot;more&quot;">',
            false,
        )
        ->assertSee(
            '<meta property="og:description" content="Claim safely &amp; enjoy.">',
            false,
        )
        ->assertSee('<meta property="og:image" content="https://share.example.test/x/claim/'.$voucher->code.'/share-card.png">', false)
        ->assertDontSee('javascript:alert', false)
        ->assertDontSee('<script>alert', false);

    Http::assertNothingSent();
});
