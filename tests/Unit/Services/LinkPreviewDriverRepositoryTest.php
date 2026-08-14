<?php

declare(strict_types=1);

use LBHurtado\XChange\Services\LinkPreview\Canonicalizers\YouTubeVideoCanonicalizer;
use LBHurtado\XChange\Services\LinkPreview\LinkCanonicalizerRegistry;
use LBHurtado\XChange\Services\LinkPreview\LinkPreviewDriverData;
use LBHurtado\XChange\Services\LinkPreview\LinkPreviewDriverRepository;
use Symfony\Component\Yaml\Yaml;

it('loads the package Spotify driver and keeps YouTube disabled by default', function () {
    $drivers = app(LinkPreviewDriverRepository::class)->all();

    expect($drivers)->toHaveKey('spotify')
        ->and($drivers)->not->toHaveKey('youtube')
        ->and($drivers['spotify']->canonicalizer)->toBe('strip_query')
        ->and($drivers['spotify']->oEmbedEndpoint)->toBe('https://open.spotify.com/oembed')
        ->and($drivers['spotify']->imageHosts)->toContain('i.scdn.co');
});

it('validates every package link-preview manifest', function () {
    $diagnostics = app(LinkPreviewDriverRepository::class)->diagnostics();

    expect($diagnostics)->toHaveCount(2)
        ->and(collect($diagnostics)->pluck('key')->all())->toBe([
            'spotify',
            'youtube',
        ])
        ->and(collect($diagnostics)->every('valid'))->toBeTrue()
        ->and(collect($diagnostics)->firstWhere('key', 'spotify')['enabled'])->toBeTrue()
        ->and(collect($diagnostics)->firstWhere('key', 'youtube')['enabled'])->toBeFalse();
});

it('rejects unsafe or executable driver manifest values', function (array $override, string $message) {
    $manifest = Yaml::parseFile(
        dirname(__DIR__, 3).'/config/link-preview-drivers/spotify.yaml',
    );

    expect($manifest)->toBeArray();

    foreach ($override as $path => $value) {
        data_set($manifest, $path, $value);
    }

    expect(fn () => LinkPreviewDriverData::fromManifest(
        $manifest,
        app(LinkCanonicalizerRegistry::class),
    ))->toThrow(InvalidArgumentException::class, $message);
})->with([
    'unregistered canonicalizer' => [
        ['canonicalization.strategy' => 'App\\ArbitraryCallback'],
        'is not registered',
    ],
    'IP page host' => [
        ['match.hosts' => ['127.0.0.1']],
        'contains unsafe host',
    ],
    'wildcard image host' => [
        ['artwork.hosts' => ['*.spotifycdn.com']],
        'contains unsafe host',
    ],
    'HTTP metadata endpoint' => [
        ['metadata.oembed_endpoint' => 'http://open.spotify.com/oembed'],
        'endpoint is unsafe',
    ],
    'metadata endpoint on another host' => [
        ['metadata.oembed_endpoint' => 'https://example.com/oembed'],
        'endpoint is unsafe',
    ],
    'unsupported metadata strategy' => [
        ['metadata.strategies' => ['App\\MetadataFetcher']],
        'strategy is not supported',
    ],
    'SVG artwork' => [
        ['artwork.mime_types' => ['image/svg+xml']],
        'MIME type [image/svg+xml] is not supported',
    ],
    'invalid path regular expression' => [
        ['match.path_pattern' => '#[invalid#'],
        'path_pattern is invalid',
    ],
]);

it('canonicalizes YouTube watch parameters without preserving tracking data', function (
    string $url,
    ?string $expected,
) {
    $manifest = Yaml::parseFile(
        dirname(__DIR__, 3).'/config/link-preview-drivers/youtube.yaml',
    );
    $driver = LinkPreviewDriverData::fromManifest(
        $manifest,
        app(LinkCanonicalizerRegistry::class),
        true,
    );

    expect((new YouTubeVideoCanonicalizer)->canonicalize($url, $driver))
        ->toBe($expected);
})->with([
    'short tracked URL' => [
        'https://youtu.be/Hz_wdBH0fTo?si=KnyudYHL2qXGXtb5',
        'https://www.youtube.com/watch?v=Hz_wdBH0fTo',
    ],
    'watch URL' => [
        'https://www.youtube.com/watch?v=Hz_wdBH0fTo&si=tracking',
        'https://www.youtube.com/watch?v=Hz_wdBH0fTo',
    ],
    'duplicate video parameter' => [
        'https://www.youtube.com/watch?v=Hz_wdBH0fTo&v=dQw4w9WgXcQ',
        null,
    ],
    'video ID as another parameter' => [
        'https://www.youtube.com/watch?feature=share&v=Hz_wdBH0fTo',
        'https://www.youtube.com/watch?v=Hz_wdBH0fTo',
    ],
]);
