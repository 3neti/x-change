<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Claim;

use DOMDocument;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\RiderSplashArtworkSnapshotterContract;
use LBHurtado\XChange\Data\Claim\RiderSplashArtworkSnapshotData;
use Throwable;

final class DefaultRiderSplashArtworkSnapshotter implements RiderSplashArtworkSnapshotterContract
{
    /**
     * @var array<string, string>
     */
    private const array Extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function prepare(array $input): array
    {
        data_forget($input, 'metadata.custom.rider_splash_artwork');

        if (! $this->usesSplashArtwork($input)) {
            return $input;
        }

        $snapshot = $this->snapshot((string) data_get($input, 'rider.splash', ''));

        if (! $snapshot instanceof RiderSplashArtworkSnapshotData) {
            return $input;
        }

        data_set(
            $input,
            'metadata.custom.rider_splash_artwork',
            $snapshot->toArray(),
        );

        return $input;
    }

    public function capture(
        Voucher $voucher,
        bool $force = false,
    ): ?RiderSplashArtworkSnapshotData {
        if (! $this->usesSplashArtwork($voucher->instructions->toArray())) {
            return null;
        }

        $existing = $this->snapshotFor($voucher);

        if (
            ! $force
            && $existing instanceof RiderSplashArtworkSnapshotData
            && $this->exists($existing)
        ) {
            return $existing;
        }

        $snapshot = $this->snapshot(
            (string) $voucher->instructions->rider->splash,
        );

        if (! $snapshot instanceof RiderSplashArtworkSnapshotData) {
            return null;
        }

        $metadata = is_array($voucher->metadata) ? $voucher->metadata : [];
        data_set(
            $metadata,
            'instructions.metadata.custom.rider_splash_artwork',
            $snapshot->toArray(),
        );
        $voucher->forceFill(['metadata' => $metadata])->save();
        $voucher->refresh();

        return $snapshot;
    }

    public function dataUrl(Voucher $voucher): ?string
    {
        $snapshot = $this->snapshotFor($voucher);

        if (! $snapshot instanceof RiderSplashArtworkSnapshotData) {
            return null;
        }

        try {
            $contents = Storage::disk($this->disk())
                ->get($this->path($snapshot));
        } catch (Throwable) {
            return null;
        }

        if (
            $contents === ''
            || ! hash_equals($snapshot->sha256, hash('sha256', $contents))
        ) {
            return null;
        }

        return 'data:'.$snapshot->mimeType.';base64,'.base64_encode($contents);
    }

    private function snapshot(string $splash): ?RiderSplashArtworkSnapshotData
    {
        if (
            ! config('x-change.claim.share.splash_artwork.enabled', true)
            || trim($splash) === ''
        ) {
            return null;
        }

        $source = $this->firstImageSource($splash);
        $image = $source === null ? null : $this->resolveImage($source);

        if ($image === null) {
            return null;
        }

        [$contents, $mimeType] = $image;
        $dimensions = @getimagesizefromstring($contents);
        $maximumPixels = max(
            1200 * 630,
            (int) config(
                'x-change.claim.share.maximum_artwork_pixels',
                16_000_000,
            ),
        );

        if (
            ! is_array($dimensions)
            || ! isset($dimensions[0], $dimensions[1], $dimensions['mime'])
            || $dimensions['mime'] !== $mimeType
            || $dimensions[0] < 1
            || $dimensions[1] < 1
            || ($dimensions[0] * $dimensions[1]) > $maximumPixels
        ) {
            return null;
        }

        $snapshot = new RiderSplashArtworkSnapshotData(
            sha256: hash('sha256', $contents),
            mimeType: $mimeType,
            width: $dimensions[0],
            height: $dimensions[1],
            capturedAt: now()->toIso8601String(),
        );

        try {
            $stored = Storage::disk($this->disk())->put(
                $this->path($snapshot),
                $contents,
            );
        } catch (Throwable) {
            return null;
        }

        return $stored ? $snapshot : null;
    }

    /**
     * @return null|array{0: string, 1: string}
     */
    private function resolveImage(string $source): ?array
    {
        if (preg_match(
            '/^data:(image\/(?:png|jpeg|webp));base64,([A-Za-z0-9+\/=]+)$/',
            $source,
            $matches,
        ) === 1) {
            $contents = base64_decode($matches[2], true);

            return is_string($contents) && $this->withinByteLimit($contents)
                ? [$contents, $matches[1]]
                : null;
        }

        $url = $this->safeRemoteUrl($this->normalizeGitHubUrl($source));

        if ($url === null) {
            return null;
        }

        try {
            $response = Http::accept(implode(', ', array_keys(self::Extensions)))
                ->connectTimeout(max(
                    1,
                    (int) config(
                        'x-change.claim.share.splash_artwork.connect_timeout_seconds',
                        3,
                    ),
                ))
                ->timeout(max(
                    1,
                    (int) config(
                        'x-change.claim.share.splash_artwork.timeout_seconds',
                        6,
                    ),
                ))
                ->withoutRedirecting()
                ->retry(
                    max(
                        1,
                        (int) config(
                            'x-change.claim.share.splash_artwork.retry_attempts',
                            3,
                        ),
                    ),
                    max(
                        0,
                        (int) config(
                            'x-change.claim.share.splash_artwork.retry_sleep_milliseconds',
                            150,
                        ),
                    ),
                    static function (
                        Throwable $exception,
                        PendingRequest $request,
                    ): bool {
                        if ($exception instanceof ConnectionException) {
                            return true;
                        }

                        return $exception instanceof RequestException
                            && (
                                $exception->response->serverError()
                                || $exception->response->status() === 429
                            );
                    },
                    throw: false,
                )
                ->get($url);
        } catch (Throwable) {
            return null;
        }

        $mimeType = strtolower(trim(explode(
            ';',
            (string) $response->header('Content-Type'),
        )[0]));
        $contents = $response->body();

        return $response->successful()
            && isset(self::Extensions[$mimeType])
            && $this->withinByteLimit($contents)
                ? [$contents, $mimeType]
                : null;
    }

    private function withinByteLimit(string $contents): bool
    {
        return $contents !== ''
            && strlen($contents) <= max(
                1024,
                (int) config(
                    'x-change.claim.share.splash_artwork.maximum_image_bytes',
                    2 * 1024 * 1024,
                ),
            );
    }

    private function firstImageSource(string $splash): ?string
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);

        try {
            if (! $document->loadHTML(
                $splash,
                LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR,
            )) {
                return null;
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        foreach ($document->getElementsByTagName('img') as $image) {
            $source = trim($image->getAttribute('src'));

            if ($source !== '') {
                return $source;
            }
        }

        return null;
    }

    private function normalizeGitHubUrl(string $source): string
    {
        $parts = parse_url($source);

        if (
            ! is_array($parts)
            || strtolower((string) ($parts['host'] ?? '')) !== 'github.com'
        ) {
            return $source;
        }

        $path = (string) ($parts['path'] ?? '');

        if (preg_match(
            '#^/([^/]+)/([^/]+)/blob/([^/]+)/(.+)$#',
            $path,
            $matches,
        ) !== 1) {
            return $source;
        }

        return sprintf(
            'https://raw.githubusercontent.com/%s/%s/%s/%s',
            $matches[1],
            $matches[2],
            $matches[3],
            $matches[4],
        );
    }

    private function safeRemoteUrl(string $source): ?string
    {
        $parts = parse_url(trim($source));
        $configuredHosts = config(
            'x-change.claim.share.splash_artwork.allowed_hosts',
            ['raw.githubusercontent.com'],
        );
        $allowedHosts = array_values(array_filter(
            array_map(
                static fn (mixed $host): string => is_string($host)
                    ? strtolower(trim($host))
                    : '',
                is_array($configuredHosts) ? $configuredHosts : [],
            ),
        ));

        if (
            ! is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || ! in_array(
                strtolower((string) ($parts['host'] ?? '')),
                $allowedHosts,
                true,
            )
            || isset($parts['user'])
            || isset($parts['pass'])
            || (isset($parts['port']) && (int) $parts['port'] !== 443)
        ) {
            return null;
        }

        return $source;
    }

    /**
     * @param  array<string, mixed>  $instructions
     */
    private function usesSplashArtwork(array $instructions): bool
    {
        $artwork = data_get($instructions, 'rider.stamp.artwork_source');

        if (is_string($artwork)) {
            return $artwork === 'splash';
        }

        return data_get($instructions, 'rider.stamp.source') === 'splash'
            || data_get($instructions, 'rider.og_source') === 'splash';
    }

    private function snapshotFor(
        Voucher $voucher,
    ): ?RiderSplashArtworkSnapshotData {
        return RiderSplashArtworkSnapshotData::fromArray(data_get(
            $voucher,
            'instructions.metadata.custom.rider_splash_artwork',
        ));
    }

    private function exists(RiderSplashArtworkSnapshotData $snapshot): bool
    {
        try {
            return Storage::disk($this->disk())->exists($this->path($snapshot));
        } catch (Throwable) {
            return false;
        }
    }

    private function path(RiderSplashArtworkSnapshotData $snapshot): string
    {
        $directory = trim((string) config(
            'x-change.claim.share.splash_artwork.directory',
            'x-change/claim/splash-artwork',
        ), '/');

        return $directory.'/'.$snapshot->sha256.'.'.self::Extensions[$snapshot->mimeType];
    }

    private function disk(): string
    {
        return (string) config(
            'x-change.claim.share.splash_artwork.disk',
            'local',
        );
    }
}
