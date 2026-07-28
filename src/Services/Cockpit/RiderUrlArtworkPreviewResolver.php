<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

final class RiderUrlArtworkPreviewResolver
{
    /**
     * @var list<string>
     */
    private const array ArtworkMimeTypes = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    /**
     * @return array{
     *     available: bool,
     *     source: string,
     *     title: string,
     *     description: string,
     *     image_url: ?string,
     *     reference: string
     * }
     */
    public function resolve(string $url): array
    {
        $provider = $this->providerFor($url);

        if ($provider === null) {
            return $this->unavailable();
        }

        $cacheKey = 'x-change:cockpit:rider-url-artwork:'.hash(
            'sha256',
            $provider['key'].'|'.$provider['url'],
        );
        $cacheTtl = max(
            60,
            (int) config(
                'x-change.cockpit.quick_generate.url_artwork.cache_ttl_seconds',
                3600,
            ),
        );

        return Cache::remember(
            $cacheKey,
            $cacheTtl,
            fn (): array => $this->resolveProviderArtwork($provider),
        );
    }

    /**
     * @param  array{
     *     key: string,
     *     label: string,
     *     url: string,
     *     image_hosts: list<string>
     * }  $provider
     * @return array{
     *     available: bool,
     *     source: string,
     *     title: string,
     *     description: string,
     *     image_url: ?string,
     *     reference: string
     * }
     */
    private function resolveProviderArtwork(array $provider): array
    {
        try {
            $response = Http::accept('text/html, application/xhtml+xml')
                ->connectTimeout(max(
                    1,
                    (int) config(
                        'x-change.cockpit.quick_generate.url_artwork.connect_timeout_seconds',
                        3,
                    ),
                ))
                ->timeout(max(
                    1,
                    (int) config(
                        'x-change.cockpit.quick_generate.url_artwork.timeout_seconds',
                        6,
                    ),
                ))
                ->withoutRedirecting()
                ->get($provider['url']);
        } catch (Throwable) {
            return $this->unavailable();
        }

        $contentType = strtolower(trim(explode(
            ';',
            (string) $response->header('Content-Type'),
        )[0]));
        $document = $response->body();
        $maximumDocumentBytes = max(
            1024,
            (int) config(
                'x-change.cockpit.quick_generate.url_artwork.maximum_document_bytes',
                512 * 1024,
            ),
        );

        if (
            ! $response->successful()
            || ! in_array($contentType, ['text/html', 'application/xhtml+xml'], true)
            || $document === ''
            || strlen($document) > $maximumDocumentBytes
        ) {
            return $this->unavailable();
        }

        $metadata = $this->openGraphMetadata($document);
        $imageUrl = $this->safeArtworkUrl(
            $metadata['image'] ?? null,
            $provider['image_hosts'],
        );
        $imageDataUrl = $imageUrl === null
            ? null
            : $this->fetchArtworkDataUrl($imageUrl);

        if ($imageDataUrl === null) {
            return $this->unavailable();
        }

        return [
            'available' => true,
            'source' => $provider['key'],
            'title' => $this->safeText(
                $metadata['title'] ?? null,
                $provider['label'],
                160,
            ),
            'description' => $this->safeText(
                $metadata['description'] ?? null,
                $provider['label'],
                240,
            ),
            'image_url' => $imageDataUrl,
            'reference' => $provider['label'],
        ];
    }

    /**
     * @return null|array{
     *     key: string,
     *     label: string,
     *     url: string,
     *     image_hosts: list<string>
     * }
     */
    private function providerFor(string $url): ?array
    {
        $parts = parse_url(trim($url));

        if (
            ! is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || blank($parts['host'] ?? null)
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            return null;
        }

        $host = strtolower((string) $parts['host']);
        $path = '/'.ltrim((string) ($parts['path'] ?? ''), '/');
        $providers = config(
            'x-change.cockpit.quick_generate.url_artwork.providers',
            [],
        );

        foreach (is_array($providers) ? $providers : [] as $key => $provider) {
            if (
                ! is_string($key)
                || ! is_array($provider)
                || ! ($provider['enabled'] ?? false)
            ) {
                continue;
            }

            $pageHosts = array_values(array_filter(
                $provider['page_hosts'] ?? [],
                is_string(...),
            ));
            $pathPattern = $provider['path_pattern'] ?? null;

            if (
                ! in_array($host, $pageHosts, true)
                || ! is_string($pathPattern)
                || preg_match($pathPattern, $path) !== 1
            ) {
                continue;
            }

            $canonicalUrl = 'https://'.$host.rtrim($path, '/');

            if (! ($provider['strip_query'] ?? true) && isset($parts['query'])) {
                $canonicalUrl .= '?'.$parts['query'];
            }

            return [
                'key' => $key,
                'label' => is_string($provider['label'] ?? null)
                    ? $provider['label']
                    : Str::headline($key),
                'url' => $canonicalUrl,
                'image_hosts' => array_values(array_filter(
                    $provider['image_hosts'] ?? [],
                    is_string(...),
                )),
            ];
        }

        return null;
    }

    /**
     * @return array{title?: string, description?: string, image?: string}
     */
    private function openGraphMetadata(string $document): array
    {
        $dom = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);

        try {
            if (! $dom->loadHTML($document, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR)) {
                return [];
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $metadata = [];

        foreach ($dom->getElementsByTagName('meta') as $meta) {
            $property = strtolower(trim(
                $meta->getAttribute('property') ?: $meta->getAttribute('name'),
            ));
            $content = trim($meta->getAttribute('content'));

            if ($content === '') {
                continue;
            }

            $key = match ($property) {
                'og:title' => 'title',
                'og:description' => 'description',
                'og:image', 'og:image:secure_url', 'twitter:image' => 'image',
                default => null,
            };

            if ($key !== null && ! isset($metadata[$key])) {
                $metadata[$key] = $content;
            }
        }

        return $metadata;
    }

    /**
     * @param  list<string>  $approvedHosts
     */
    private function safeArtworkUrl(mixed $value, array $approvedHosts): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $url = trim($value);
        $parts = parse_url($url);

        if (
            $url === ''
            || ! is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || blank($parts['host'] ?? null)
            || ! in_array(
                strtolower((string) $parts['host']),
                $approvedHosts,
                true,
            )
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            return null;
        }

        return $url;
    }

    private function fetchArtworkDataUrl(string $url): ?string
    {
        try {
            $response = Http::accept(implode(', ', self::ArtworkMimeTypes))
                ->connectTimeout(max(
                    1,
                    (int) config(
                        'x-change.cockpit.quick_generate.url_artwork.connect_timeout_seconds',
                        3,
                    ),
                ))
                ->timeout(max(
                    1,
                    (int) config(
                        'x-change.cockpit.quick_generate.url_artwork.timeout_seconds',
                        6,
                    ),
                ))
                ->withoutRedirecting()
                ->get($url);
        } catch (Throwable) {
            return null;
        }

        $mimeType = strtolower(trim(explode(
            ';',
            (string) $response->header('Content-Type'),
        )[0]));
        $body = $response->body();
        $maximumBytes = max(
            1024,
            (int) config(
                'x-change.cockpit.quick_generate.url_artwork.maximum_image_bytes',
                2 * 1024 * 1024,
            ),
        );

        if (
            ! $response->successful()
            || ! in_array($mimeType, self::ArtworkMimeTypes, true)
            || $body === ''
            || strlen($body) > $maximumBytes
        ) {
            return null;
        }

        return 'data:'.$mimeType.';base64,'.base64_encode($body);
    }

    private function safeText(mixed $value, string $fallback, int $limit): string
    {
        if (! is_string($value)) {
            return $fallback;
        }

        $text = trim(preg_replace(
            '/\s+/',
            ' ',
            html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5),
        ) ?? '');

        return $text === '' ? $fallback : Str::limit($text, $limit);
    }

    /**
     * @return array{
     *     available: bool,
     *     source: string,
     *     title: string,
     *     description: string,
     *     image_url: null,
     *     reference: string
     * }
     */
    private function unavailable(): array
    {
        return [
            'available' => false,
            'source' => 'link',
            'title' => 'Action Link',
            'description' => 'Artwork is not available for this link.',
            'image_url' => null,
            'reference' => 'Action URL',
        ];
    }
}
