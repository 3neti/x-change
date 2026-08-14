<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use Illuminate\Support\Str;
use LBHurtado\XChange\Enums\RiderLibraryEntryKind;
use LBHurtado\XRider\Support\RiderHtmlSanitizer;
use RuntimeException;

final readonly class RiderLibraryPayloadNormalizer
{
    public function __construct(
        private RiderHtmlSanitizer $htmlSanitizer,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{url: string}|array{splash: string, format: string, meta?: array{sanitized: true, html_profile: string}}
     */
    public function normalize(
        RiderLibraryEntryKind $kind,
        array $payload,
    ): array {
        if ($kind === RiderLibraryEntryKind::Url) {
            return [
                'url' => trim((string) ($payload['url'] ?? '')),
            ];
        }

        $format = strtolower(trim((string) ($payload['format'] ?? 'plain')));
        $format = in_array($format, ['plain', 'markdown', 'html'], true)
            ? $format
            : 'plain';
        $splash = trim((string) ($payload['splash'] ?? ''));

        if ($format !== 'html') {
            return [
                'splash' => $splash,
                'format' => $format,
            ];
        }

        return [
            'splash' => $this->htmlSanitizer->sanitizeSplash($splash),
            'format' => 'html',
            'meta' => [
                'sanitized' => true,
                'html_profile' => 'rider_splash',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function fingerprint(
        RiderLibraryEntryKind $kind,
        array $payload,
    ): string {
        $key = (string) config('app.key');

        if ($key === '') {
            throw new RuntimeException('Rider Library requires a configured application key.');
        }

        return hash_hmac('sha256', json_encode([
            'schema' => 'x-change.rider-library-entry.v1',
            'kind' => $kind->value,
            'payload' => $payload,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $key);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function defaultLabel(
        RiderLibraryEntryKind $kind,
        array $payload,
    ): string {
        if ($kind === RiderLibraryEntryKind::Url) {
            $host = parse_url((string) ($payload['url'] ?? ''), PHP_URL_HOST);

            return is_string($host) && $host !== '' ? $host : 'Rider Link';
        }

        $content = Str::of(strip_tags((string) ($payload['splash'] ?? '')))
            ->squish()
            ->limit(60, '…')
            ->toString();

        return $content !== '' ? $content : 'Rider Splash';
    }
}
