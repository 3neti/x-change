<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\LinkPreview;

use LBHurtado\XChange\Services\LinkPreview\Canonicalizers\LinkCanonicalizer;
use LBHurtado\XChange\Services\LinkPreview\Canonicalizers\PreserveQueryCanonicalizer;
use LBHurtado\XChange\Services\LinkPreview\Canonicalizers\StripQueryCanonicalizer;
use LBHurtado\XChange\Services\LinkPreview\Canonicalizers\YouTubeVideoCanonicalizer;

final class LinkCanonicalizerRegistry
{
    /**
     * @var array<string, class-string<LinkCanonicalizer>>
     */
    private const array Canonicalizers = [
        'preserve_query' => PreserveQueryCanonicalizer::class,
        'strip_query' => StripQueryCanonicalizer::class,
        'youtube_video' => YouTubeVideoCanonicalizer::class,
    ];

    public function has(string $key): bool
    {
        return isset(self::Canonicalizers[$key]);
    }

    public function canonicalize(
        string $key,
        string $url,
        LinkPreviewDriverData $driver,
    ): ?string {
        $canonicalizer = $this->resolve($key);

        return $canonicalizer?->canonicalize($url, $driver);
    }

    private function resolve(string $key): ?LinkCanonicalizer
    {
        $class = self::Canonicalizers[$key] ?? null;

        return is_string($class) ? new $class : null;
    }
}
