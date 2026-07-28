<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Claim;

final readonly class RiderStampArtifactData
{
    public const string Schema = 'x-change.rider-stamp-artifact.v1';

    public const string MimeType = 'image/png';

    public const int Width = 1200;

    public const int Height = 630;

    public const string ManifestVersion = 'x-change.rider-stamp-render-manifest.v1';

    public function __construct(
        public string $sha256,
        public int $width,
        public int $height,
        public string $mimeType,
        public string $renderingManifestVersion,
        public string $renderedAt,
    ) {}

    /**
     * @return array{
     *     schema: string,
     *     sha256: string,
     *     width: int,
     *     height: int,
     *     mime_type: string,
     *     rendering_manifest_version: string,
     *     rendered_at: string
     * }
     */
    public function toArray(): array
    {
        return [
            'schema' => self::Schema,
            'sha256' => $this->sha256,
            'width' => $this->width,
            'height' => $this->height,
            'mime_type' => $this->mimeType,
            'rendering_manifest_version' => $this->renderingManifestVersion,
            'rendered_at' => $this->renderedAt,
        ];
    }

    public static function fromArray(mixed $value): ?self
    {
        if (
            ! is_array($value)
            || ($value['schema'] ?? null) !== self::Schema
            || preg_match('/^[a-f0-9]{64}$/', (string) ($value['sha256'] ?? '')) !== 1
            || (int) ($value['width'] ?? 0) !== self::Width
            || (int) ($value['height'] ?? 0) !== self::Height
            || ($value['mime_type'] ?? null) !== self::MimeType
            || ! is_string($value['rendering_manifest_version'] ?? null)
            || trim($value['rendering_manifest_version']) === ''
            || ! is_string($value['rendered_at'] ?? null)
            || trim($value['rendered_at']) === ''
        ) {
            return null;
        }

        return new self(
            sha256: (string) $value['sha256'],
            width: (int) $value['width'],
            height: (int) $value['height'],
            mimeType: (string) $value['mime_type'],
            renderingManifestVersion: $value['rendering_manifest_version'],
            renderedAt: $value['rendered_at'],
        );
    }
}
