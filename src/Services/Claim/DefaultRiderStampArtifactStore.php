<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Claim;

use Illuminate\Support\Facades\Storage;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\RiderStampArtifactStoreContract;
use LBHurtado\XChange\Contracts\RiderStampClaimCardComposerContract;
use LBHurtado\XChange\Data\Claim\ClaimShareCardData;
use LBHurtado\XChange\Data\Claim\RiderStampArtifactData;
use LBHurtado\XChange\Exceptions\RiderStampArtifactUnavailable;
use Throwable;

final readonly class DefaultRiderStampArtifactStore implements RiderStampArtifactStoreContract
{
    public function __construct(
        private RiderStampClaimCardComposerContract $composer,
    ) {}

    public function materialize(
        Voucher $voucher,
        string $claimUrl,
        bool $force = false,
    ): RiderStampArtifactData {
        $existing = $this->descriptor($voucher);

        if (! $force && $existing instanceof RiderStampArtifactData && $this->read($voucher) !== null) {
            return $existing;
        }

        $card = $this->composer->compose($voucher, $claimUrl);
        $artifact = $this->artifactFromContents($card->contents);
        $path = $this->path($artifact);

        try {
            $disk = Storage::disk($this->disk());

            if ($disk->exists($path)) {
                $storedContents = $disk->get($path);

                if (! hash_equals($artifact->sha256, hash('sha256', $storedContents))) {
                    throw new RiderStampArtifactUnavailable;
                }
            } elseif (! $disk->put($path, $card->contents)) {
                throw new RiderStampArtifactUnavailable;
            }
        } catch (RiderStampArtifactUnavailable $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new RiderStampArtifactUnavailable;
        }

        $metadata = is_array($voucher->metadata) ? $voucher->metadata : [];
        data_set(
            $metadata,
            'instructions.metadata.custom.rider_stamp_artifact',
            $artifact->toArray(),
        );
        $voucher->forceFill(['metadata' => $metadata])->save();
        $voucher->refresh();

        if ($this->read($voucher) === null) {
            throw new RiderStampArtifactUnavailable;
        }

        return $artifact;
    }

    public function descriptor(Voucher $voucher): ?RiderStampArtifactData
    {
        return RiderStampArtifactData::fromArray(data_get(
            $voucher,
            'instructions.metadata.custom.rider_stamp_artifact',
        ));
    }

    public function read(Voucher $voucher): ?ClaimShareCardData
    {
        $artifact = $this->descriptor($voucher);

        if (! $artifact instanceof RiderStampArtifactData) {
            return null;
        }

        try {
            $contents = Storage::disk($this->disk())->get($this->path($artifact));
        } catch (Throwable) {
            return null;
        }

        if (! is_string($contents)) {
            return null;
        }

        try {
            $verified = $this->artifactFromContents($contents);
        } catch (RiderStampArtifactUnavailable) {
            return null;
        }

        if (! hash_equals($artifact->sha256, $verified->sha256)) {
            return null;
        }

        return new ClaimShareCardData(
            contents: $contents,
            etag: '"'.$artifact->sha256.'"',
            immutable: true,
        );
    }

    private function artifactFromContents(string $contents): RiderStampArtifactData
    {
        $dimensions = @getimagesizefromstring($contents);

        if (
            $contents === ''
            || ! is_array($dimensions)
            || ($dimensions['mime'] ?? null) !== RiderStampArtifactData::MimeType
            || (int) ($dimensions[0] ?? 0) !== RiderStampArtifactData::Width
            || (int) ($dimensions[1] ?? 0) !== RiderStampArtifactData::Height
        ) {
            throw new RiderStampArtifactUnavailable;
        }

        return new RiderStampArtifactData(
            sha256: hash('sha256', $contents),
            width: (int) $dimensions[0],
            height: (int) $dimensions[1],
            mimeType: (string) $dimensions['mime'],
            renderingManifestVersion: (string) config(
                'x-change.claim.share.artifact.rendering_manifest_version',
                RiderStampArtifactData::ManifestVersion,
            ),
            renderedAt: now()->toIso8601String(),
        );
    }

    private function path(RiderStampArtifactData $artifact): string
    {
        $directory = trim((string) config(
            'x-change.claim.share.artifact.directory',
            'x-change/claim/stamp-artifacts',
        ), '/');

        return $directory.'/'.$artifact->sha256.'.png';
    }

    private function disk(): string
    {
        return (string) config(
            'x-change.claim.share.artifact.disk',
            'local',
        );
    }
}
