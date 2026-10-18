<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use LBHurtado\Voucher\Data\RiderStampData;
use LBHurtado\XChange\Services\Stamp\StampCatalog;
use LBHurtado\XChange\Services\Stamp\StampPresentation;

final class RiderStampDesignRegistry
{
    public const int StampSchemaVersion = 3;

    /** @return list<int> */
    public static function supportedStampSchemaVersions(): array
    {
        $versions = [
            RiderStampData::LEGACY_SCHEMA_VERSION,
            RiderStampData::SCHEMA_VERSION,
            self::StampSchemaVersion,
        ];

        if (defined(RiderStampData::class.'::COMPOSITION_SCHEMA_VERSION')) {
            $versions[] = (int) constant(
                RiderStampData::class.'::COMPOSITION_SCHEMA_VERSION',
            );
        }

        sort($versions);

        return array_values(array_unique($versions));
    }

    /**
     * @return array<string, array{default_version: int, versions: array<int, array{palette: array<string, array{int, int, int}>}>}>
     */
    public function all(): array
    {
        /** @var array<string, array{default_version: int, versions: array<int, array{palette: array<string, array{int, int, int}>}>}> $designs */
        $designs = config('x-change.experience.stamp_designs', []);
        foreach (app(StampCatalog::class)->catalog()->all() as $design) {
            $rgb = static fn (string $hex): array => [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];
            $background = $rgb($design->document['background']);
            $designs[$design->id()]['default_version'] = max($designs[$design->id()]['default_version'] ?? 0, $design->revision());
            $designs[$design->id()]['versions'][$design->revision()] = ['palette' => ['start' => $background, 'end' => $background, 'glow_primary' => $background, 'glow_secondary' => $background]];
        }

        return $designs;
    }

    /** @return list<int> */
    public function versions(mixed $designId): array
    {
        $id = is_string($designId) ? $designId : (string) config('x-change.experience.default_system_stamp_design', 'steampunk');

        return array_keys($this->all()[$id]['versions'] ?? []);
    }

    /**
     * @return array{version: int, palette: array<string, array{int, int, int}>}
     */
    public function resolve(?string $designId, ?int $designVersion): array
    {
        $resolvedId = filled($designId)
            ? (string) $designId
            : (string) config(
                'x-change.experience.default_stamp_design',
                'x-change-default',
            );
        $design = $this->all()[$resolvedId] ?? null;

        if (! is_array($design)) {
            throw new InvalidArgumentException("Unregistered Rider Stamp design [{$resolvedId}].");
        }

        $defaultVersion = (int) ($design['default_version'] ?? 0);
        $resolvedVersion = $designVersion ?? $defaultVersion;
        $version = $design['versions'][$resolvedVersion] ?? null;

        if ($defaultVersion < 1 || ! is_array($version)) {
            throw new InvalidArgumentException(
                "Unsupported Rider Stamp design version [{$resolvedId}@{$resolvedVersion}].",
            );
        }

        return [
            'version' => $resolvedVersion,
            'palette' => $version['palette'],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function materialize(array $input): array
    {
        $stamp = data_get($input, 'rider.stamp');
        $stamp = is_array($stamp) ? $stamp : [];
        $designId = data_get($stamp, 'design_id');
        $designId = is_string($designId) && trim($designId) !== ''
            ? trim($designId)
            : (string) config(
                'x-change.experience.default_system_stamp_design',
                'steampunk',
            );
        $designVersion = data_get($stamp, 'design_version');
        $designVersion = is_numeric($designVersion)
            ? (int) $designVersion
            : null;
        $design = $this->resolve($designId, $designVersion);

        data_set($stamp, 'version', self::StampSchemaVersion);
        data_set($stamp, 'design_id', $designId);
        data_set($stamp, 'design_version', (int) $design['version']);
        data_set($input, 'rider.stamp', $stamp);
        $customArtwork = in_array(data_get($stamp, 'artwork_source'), ['url', 'splash'], true)
            && data_get($stamp, 'artwork_treatment') !== 'text';
        if (! str_starts_with($designId, 'x-change-') && ! $customArtwork) {
            $candidate = data_get($input, 'metadata.custom.stamp_presentation');
            if ($candidate !== null && ! is_array($candidate)) {
                throw ValidationException::withMessages(['metadata.custom.stamp_presentation' => 'Stamp appearance must be an object.']);
            }
            $snapshot = app(StampPresentation::class)->snapshot(app(StampCatalog::class)->resolve($designId, (int) $design['version']), $candidate);
            if ($snapshot !== null) {
                data_set($input, 'metadata.custom.stamp_presentation', $snapshot);
            }
        }

        return $input;
    }

    /**
     * Keep x-change deployable while the owning voucher package moves from the
     * v2 composition contract to the v3 design snapshot contract.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function normalizeForInstalledVoucherContract(array $input): array
    {
        if (defined(RiderStampData::class.'::COMPOSITION_SCHEMA_VERSION')) {
            return $input;
        }

        $designId = (string) data_get($input, 'rider.stamp.design_id');
        $designVersion = (int) data_get($input, 'rider.stamp.design_version');

        data_set($input, 'metadata.custom.rider_stamp_design', [
            'id' => $designId,
            'version' => $designVersion,
        ]);
        data_forget($input, 'rider.stamp.design_id');
        data_forget($input, 'rider.stamp.design_version');
        data_set($input, 'rider.stamp.version', RiderStampData::SCHEMA_VERSION);

        return $input;
    }

    /**
     * @return array{start: array{int, int, int}, end: array{int, int, int}, glow_primary: array{int, int, int}, glow_secondary: array{int, int, int}}
     */
    public function palette(?string $designId, ?int $designVersion): array
    {
        $design = $this->resolve($designId, $designVersion);

        return $design['palette'];
    }
}
