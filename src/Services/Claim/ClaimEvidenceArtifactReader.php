<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Claim;

use Illuminate\Support\Facades\Storage;
use LBHurtado\XChange\Exceptions\InstanceKeepsakeException;
use LBHurtado\XChange\Models\VoucherClaimEvidence;
use Throwable;

final class ClaimEvidenceArtifactReader
{
    /** @return array{contents:string,mime_type:string,extension:string,size:int,sha256:string} */
    public function stored(VoucherClaimEvidence $evidence): array
    {
        $diskName = trim((string) $evidence->artifact_disk);
        $path = trim((string) $evidence->artifact_path);

        if ($diskName === '' || $path === '' || ! filled($evidence->sha256)) {
            throw new InstanceKeepsakeException('missing_artifact', 'The evidence artifact reference is incomplete.');
        }

        try {
            $disk = Storage::disk($diskName);

            if (! $disk->exists($path)) {
                throw new InstanceKeepsakeException('missing_artifact', 'The evidence artifact is not available.');
            }

            $contents = $disk->get($path);
        } catch (InstanceKeepsakeException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new InstanceKeepsakeException('storage_unavailable', 'The evidence storage could not be read.');
        }

        return $this->verify(
            contents: $contents,
            declaredMime: filled($evidence->mime_type) ? (string) $evidence->mime_type : null,
            expectedSize: $evidence->size !== null ? (int) $evidence->size : null,
            expectedHash: (string) $evidence->sha256,
        );
    }

    /** @return array{contents:string,mime_type:string,extension:string,size:int,sha256:string} */
    public function legacy(string $value, string $requirementKey): array
    {
        if ($requirementKey === 'location') {
            $location = json_decode($value, true);

            if (! is_array($location) || ! is_string($location['map'] ?? null)) {
                throw new InstanceKeepsakeException('not_retained', 'The legacy location map was not retained.');
            }

            $value = $location['map'];
        }

        $declaredMime = null;

        if (preg_match('/^data:([^;]+);base64,(.+)$/s', $value, $matches) === 1) {
            $declaredMime = mb_strtolower(trim($matches[1]));
            $value = $matches[2];
        }

        $contents = base64_decode(preg_replace('/\s+/', '', $value) ?? '', true);

        if (! is_string($contents) || $contents === '') {
            throw new InstanceKeepsakeException('malformed_legacy_artifact', 'The legacy evidence image is malformed.');
        }

        return $this->verify($contents, $declaredMime);
    }

    public function isStoredPointer(string $value): bool
    {
        $decoded = json_decode($value, true);

        return is_array($decoded)
            && ($decoded['stored'] ?? false) === true
            && filled($decoded['claim_evidence_id'] ?? null);
    }

    /** @return array{contents:string,mime_type:string,extension:string,size:int,sha256:string} */
    private function verify(
        string $contents,
        ?string $declaredMime = null,
        ?int $expectedSize = null,
        ?string $expectedHash = null,
    ): array {
        $size = strlen($contents);

        if ($size < 1) {
            throw new InstanceKeepsakeException('missing_artifact', 'The evidence artifact is empty.');
        }

        if ($expectedSize !== null && $expectedSize !== $size) {
            throw new InstanceKeepsakeException('integrity_mismatch', 'The evidence artifact size does not match its record.');
        }

        $hash = hash('sha256', $contents);

        if ($expectedHash !== null && ! hash_equals(mb_strtolower($expectedHash), $hash)) {
            throw new InstanceKeepsakeException('integrity_mismatch', 'The evidence artifact checksum does not match its record.');
        }

        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents) ?: '';

        if (! in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new InstanceKeepsakeException('unsupported_mime', 'The evidence artifact is not a supported image.');
        }

        if ($declaredMime !== null && mb_strtolower(trim($declaredMime)) !== $mimeType) {
            throw new InstanceKeepsakeException('integrity_mismatch', 'The evidence artifact MIME type does not match its record.');
        }

        return [
            'contents' => $contents,
            'mime_type' => $mimeType,
            'extension' => match ($mimeType) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
            },
            'size' => $size,
            'sha256' => $hash,
        ];
    }
}
