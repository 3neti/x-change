<?php

declare(strict_types=1);

namespace LBHurtado\XChange\ClaimWalkthrough;

final class RiderStampPreviewPayloadFactory
{
    /**
     * @param  array<string, mixed>  $fixture
     * @return array<string, mixed>
     */
    public function make(array $fixture): array
    {
        $rider = data_get($fixture, 'rider', []);
        $rider = is_array($rider) ? $rider : [];
        $source = $this->source(
            data_get($rider, 'stamp.source'),
            data_get($rider, 'og_source'),
        );
        $message = trim((string) data_get($rider, 'message', ''));
        $url = trim((string) data_get($rider, 'url', ''));
        $splash = trim((string) data_get($rider, 'splash', ''));
        $amount = (string) data_get($fixture, 'amount', '15.00');
        $composition = $this->composition($rider, $source);

        $preview = match ($source) {
            'message' => [
                'title' => $message === '' ? 'No Rider Message yet' : $message,
                'description' => 'Rider Stamp projected from the Rider Message.',
                'reference' => 'rider.message',
                'render_mode' => 'summary',
            ],
            'url' => [
                'title' => $url === '' ? 'No Rider URL yet' : $url,
                'description' => 'Rider Stamp projected from the Rider URL.',
                'reference' => 'rider.url',
                'render_mode' => 'summary',
            ],
            'splash' => [
                'title' => $this->splashTitle($splash),
                'description' => $splash === '' ? 'Rider Splash is empty.' : $this->plainText($splash),
                'reference' => 'rider.splash',
                'render_mode' => $splash === '' ? 'summary' : 'html',
            ],
            default => [
                'title' => $this->firstFilled(
                    $this->splashTitle($splash, allowFallback: false),
                    $message,
                    $url,
                    'Default x-change Rider Stamp',
                ),
                'description' => $this->firstFilled(
                    $this->plainText($splash),
                    $message,
                    'Uses the first available Rider source.',
                ),
                'reference' => 'rider.stamp.source: automatic',
                'render_mode' => $splash === '' ? 'summary' : 'html',
            ],
        };

        $title = $this->override(data_get($rider, 'stamp.title'), $preview['title']);
        $description = $this->override(
            data_get($rider, 'stamp.description'),
            $preview['description'],
        );

        return [
            'source' => $source,
            'label' => 'Rider Stamp Preview',
            'title' => $title,
            'description' => $description,
            'reference' => $preview['reference'],
            'render_mode' => $preview['render_mode'],
            'html' => $preview['render_mode'] === 'html' ? $splash : null,
            'stamp' => [
                'source' => $source,
                'artwork_source' => $composition['artwork_source'],
                'artwork_treatment' => $composition['artwork_treatment'],
                'copy_source' => $composition['copy_source'],
                'show_logo' => $composition['show_logo'],
                'show_tagline' => $composition['show_tagline'],
                'claim_marker' => $composition['claim_marker'],
                'claim_marker_position' => $composition['claim_marker_position'],
                'fit' => $this->oneOf(data_get($rider, 'stamp.fit'), ['cover', 'contain'], 'cover'),
                'position' => $this->oneOf(
                    data_get($rider, 'stamp.position'),
                    ['center', 'top', 'bottom', 'left', 'right'],
                    'center',
                ),
                'scrim' => $this->scrim(data_get($rider, 'stamp.scrim')),
                'theme' => $this->oneOf(
                    data_get($rider, 'stamp.theme'),
                    ['automatic', 'light', 'dark'],
                    'automatic',
                ),
                'version' => $composition['version'],
                'presentation_only' => true,
            ],
            'og_meta' => [
                'title' => $title,
                'description' => $description,
                'status' => 'active',
                'headline' => '{code}',
                'subtitle' => 'PHP '.number_format((float) $amount, 2),
                'tagline' => 'Tap to claim this Pay Code.',
                'url' => '{claim_url}',
                'imageUrl' => null,
                'cacheKey' => '{code}',
                'httpMaxAge' => 300,
                'message' => $message === '' ? null : $message,
                'splashHtml' => $splash === '' ? null : $splash,
                'typeBadge' => 'cash',
                'payeeBadge' => 'Pay Code',
            ],
        ];
    }

    private function source(mixed $stampSource, mixed $legacySource): string
    {
        $source = $stampSource ?? $legacySource;

        return in_array($source, ['message', 'url', 'splash'], true)
            ? (string) $source
            : 'automatic';
    }

    /**
     * @param  array<string, mixed>  $rider
     * @return array{
     *     artwork_source: string,
     *     artwork_treatment: string,
     *     copy_source: string,
     *     show_logo: bool,
     *     show_tagline: bool,
     *     claim_marker: string,
     *     claim_marker_position: string,
     *     version: int
     * }
     */
    private function composition(array $rider, string $legacySource): array
    {
        $artworkSource = $this->oneOf(
            data_get($rider, 'stamp.artwork_source'),
            ['x_change', 'url', 'splash', 'none'],
            match ($legacySource) {
                'url', 'splash' => $legacySource,
                default => 'x_change',
            },
        );
        $copySource = $this->oneOf(
            data_get($rider, 'stamp.copy_source'),
            ['automatic', 'message', 'url', 'splash', 'custom', 'none'],
            match ($legacySource) {
                'message', 'url', 'splash' => $legacySource,
                default => 'automatic',
            },
        );

        return [
            'artwork_source' => $artworkSource,
            'artwork_treatment' => $this->oneOf(
                data_get($rider, 'stamp.artwork_treatment'),
                ['automatic', 'artwork', 'text'],
                'automatic',
            ),
            'copy_source' => $copySource,
            'show_logo' => data_get($rider, 'stamp.show_logo') !== false,
            'show_tagline' => data_get($rider, 'stamp.show_tagline') !== false,
            'claim_marker' => $this->oneOf(
                data_get($rider, 'stamp.claim_marker'),
                ['none', 'code', 'qr', 'both'],
                'qr',
            ),
            'claim_marker_position' => $this->oneOf(
                data_get($rider, 'stamp.claim_marker_position'),
                ['top_left', 'top_right', 'bottom_left', 'bottom_right'],
                'bottom_right',
            ),
            'version' => 2,
        ];
    }

    private function splashTitle(string $splash, bool $allowFallback = true): string
    {
        $plain = $this->plainText($splash);

        if ($plain !== '') {
            return mb_substr($plain, 0, 90);
        }

        return $allowFallback ? 'No Rider Splash yet' : '';
    }

    private function plainText(string $value): string
    {
        $text = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5);

        return trim(preg_replace('/\s+/', ' ', $text) ?? '');
    }

    private function firstFilled(string ...$values): string
    {
        foreach ($values as $value) {
            if (trim($value) !== '') {
                return $value;
            }
        }

        return '';
    }

    private function override(mixed $value, string $fallback): string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : $fallback;
    }

    /**
     * @param  list<string>  $allowed
     */
    private function oneOf(mixed $value, array $allowed, string $fallback): string
    {
        return is_string($value) && in_array($value, $allowed, true) ? $value : $fallback;
    }

    private function scrim(mixed $value): ?int
    {
        if (! is_int($value) || $value < 0 || $value > 100) {
            return null;
        }

        return $value;
    }
}
