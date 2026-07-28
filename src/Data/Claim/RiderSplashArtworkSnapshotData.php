<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Claim;

final readonly class RiderSplashArtworkSnapshotData
{
    public const string Schema = 'x-change.rider-splash-artwork-snapshot.v1';

    public function __construct(
        public string $sha256,
        public string $mimeType,
        public int $width,
        public int $height,
        public string $capturedAt,
    ) {}

    /**
     * @return array{
     *     schema: string,
     *     sha256: string,
     *     mime_type: string,
     *     width: int,
     *     height: int,
     *     captured_at: string
     * }
     */
    public function toArray(): array
    {
        return [
            'schema' => self::Schema,
            'sha256' => $this->sha256,
            'mime_type' => $this->mimeType,
            'width' => $this->width,
            'height' => $this->height,
            'captured_at' => $this->capturedAt,
        ];
    }

    public static function fromArray(mixed $value): ?self
    {
        if (
            ! is_array($value)
            || ($value['schema'] ?? null) !== self::Schema
            || preg_match('/^[a-f0-9]{64}$/', (string) ($value['sha256'] ?? '')) !== 1
            || ! in_array(
                $value['mime_type'] ?? null,
                ['image/jpeg', 'image/png', 'image/webp'],
                true,
            )
            || ! is_numeric($value['width'] ?? null)
            || ! is_numeric($value['height'] ?? null)
            || (int) $value['width'] < 1
            || (int) $value['height'] < 1
            || ! is_string($value['captured_at'] ?? null)
        ) {
            return null;
        }

        return new self(
            sha256: (string) $value['sha256'],
            mimeType: (string) $value['mime_type'],
            width: (int) $value['width'],
            height: (int) $value['height'],
            capturedAt: $value['captured_at'],
        );
    }
}
