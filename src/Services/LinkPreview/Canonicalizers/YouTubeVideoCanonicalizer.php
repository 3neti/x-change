<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\LinkPreview\Canonicalizers;

use LBHurtado\XChange\Services\LinkPreview\LinkPreviewDriverData;

final class YouTubeVideoCanonicalizer implements LinkCanonicalizer
{
    private const string VideoIdPattern = '[A-Za-z0-9_-]{11}';

    public function canonicalize(string $url, LinkPreviewDriverData $driver): ?string
    {
        $parts = parse_url(trim($url));

        if (! is_array($parts) || ! $driver->matches($parts)) {
            return null;
        }

        $host = strtolower((string) $parts['host']);
        $path = '/'.trim((string) ($parts['path'] ?? ''), '/');
        $videoId = $host === 'youtu.be'
            ? $this->videoIdFromShortPath($path)
            : $this->videoIdFromYouTubeUrl($path, (string) ($parts['query'] ?? ''));

        if ($videoId === null) {
            return null;
        }

        return 'https://www.youtube.com/watch?v='.$videoId;
    }

    private function videoIdFromShortPath(string $path): ?string
    {
        if (preg_match('#^/('.self::VideoIdPattern.')$#', $path, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    private function videoIdFromYouTubeUrl(string $path, string $query): ?string
    {
        if ($path === '/watch') {
            $values = $this->queryValues($query, 'v');

            return count($values) === 1 && $this->isVideoId($values[0])
                ? $values[0]
                : null;
        }

        if (preg_match('#^/(?:shorts|embed|live)/('.self::VideoIdPattern.')$#', $path, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    /**
     * @return list<string>
     */
    private function queryValues(string $query, string $targetKey): array
    {
        if ($query === '') {
            return [];
        }

        $values = [];

        foreach (explode('&', $query) as $pair) {
            [$rawKey, $rawValue] = array_pad(explode('=', $pair, 2), 2, '');

            if (urldecode($rawKey) === $targetKey) {
                $values[] = urldecode($rawValue);
            }
        }

        return $values;
    }

    private function isVideoId(string $value): bool
    {
        return preg_match('#^'.self::VideoIdPattern.'$#', $value) === 1;
    }
}
