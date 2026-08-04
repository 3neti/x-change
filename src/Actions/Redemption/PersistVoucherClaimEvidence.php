<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Redemption;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use JsonException;
use LBHurtado\SettlementEnvelope\Models\EnvelopeSignal;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Enums\ClaimEvidenceKind;
use LBHurtado\XChange\Enums\ClaimEvidenceStatus;
use LBHurtado\XChange\Models\VoucherClaim;
use LBHurtado\XChange\Models\VoucherClaimEvidence;
use RuntimeException;

final class PersistVoucherClaimEvidence
{
    /**
     * @param  array<string, mixed>  $evidence
     * @return array{input_ids: list<int>, evidence_ids: list<int>}
     *
     * @throws JsonException
     */
    public function handle(Voucher $voucher, VoucherClaim $claim, array $evidence): array
    {
        $inputIds = [];
        $evidenceIds = [];

        foreach ($evidence as $name => $value) {
            $normalizedName = trim((string) $name);
            $normalizedValue = $this->encode($value);

            if ($normalizedName === '' || $normalizedValue === null) {
                continue;
            }

            $kind = $this->kind($normalizedName);
            $verified = $this->isVerified($normalizedName, $value);
            $artifact = $this->storeArtifact($voucher, $claim, $normalizedName, $value);
            $evidenceRecord = VoucherClaimEvidence::query()->firstOrCreate([
                'voucher_claim_id' => $claim->getKey(),
                'requirement_key' => $normalizedName,
            ], [
                'voucher_id' => $voucher->getKey(),
                'kind' => $kind,
                'status' => $verified
                    ? ClaimEvidenceStatus::Verified
                    : ClaimEvidenceStatus::Captured,
                'summary' => $this->summary($normalizedName, $value),
                'payload' => $this->safePayload($normalizedName, $value, $artifact !== null),
                'artifact_disk' => $artifact['disk'] ?? null,
                'artifact_path' => $artifact['path'] ?? null,
                'mime_type' => $artifact['mime_type'] ?? null,
                'size' => $artifact['size'] ?? null,
                'sha256' => $artifact['sha256'] ?? null,
                'captured_at' => now(),
                'verified_at' => $verified ? now() : null,
                'metadata' => [
                    'manifest_version' => 1,
                ],
            ]);
            $evidenceIds[] = (int) $evidenceRecord->getKey();

            /** @var Model $input */
            $input = $voucher->inputs()->create([
                'name' => $normalizedName,
                'value' => $artifact === null
                    ? $normalizedValue
                    : json_encode([
                        'claim_evidence_id' => $evidenceRecord->getKey(),
                        'stored' => true,
                    ], JSON_THROW_ON_ERROR),
            ]);
            $inputIds[] = (int) $input->getKey();

            $metadata = (array) $evidenceRecord->metadata;
            $metadata['legacy_input_id'] = $input->getKey();
            $evidenceRecord->forceFill(['metadata' => $metadata])->save();
        }

        if ($inputIds !== []) {
            $manifestHash = $this->manifestHash($claim);
            $meta = (array) $claim->meta;
            data_set($meta, 'evidence.input_ids', $inputIds);
            data_set($meta, 'evidence.record_ids', $evidenceIds);
            data_set($meta, 'evidence.manifest_version', 1);
            data_set($meta, 'evidence.manifest_sha256', $manifestHash);
            data_set($meta, 'evidence.captured_count', count($evidenceIds));
            data_set($meta, 'evidence.persisted', true);
            $claim->forceFill(['meta' => $meta])->save();
            $this->attachManifestToSettlementEnvelope($voucher, $claim, $manifestHash);
        }

        return [
            'input_ids' => $inputIds,
            'evidence_ids' => $evidenceIds,
        ];
    }

    /**
     * @throws JsonException
     */
    private function encode(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        if (! is_array($value)) {
            return null;
        }

        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    private function kind(string $name): ClaimEvidenceKind
    {
        return match (true) {
            in_array($name, ['selfie', 'signature', 'kyc_id_front', 'kyc_id_back'], true) => ClaimEvidenceKind::Image,
            $name === 'location' => ClaimEvidenceKind::Location,
            $name === 'kyc' || str_starts_with($name, 'kyc_') || $name === 'otp' || $name === 'otp_verified' => ClaimEvidenceKind::Verification,
            default => ClaimEvidenceKind::Text,
        };
    }

    private function isVerified(string $name, mixed $value): bool
    {
        if ($name === 'otp_verified') {
            return $value === true || $value === 1 || $value === '1';
        }

        if (! in_array($name, ['otp', 'kyc'], true) || ! is_array($value)) {
            return false;
        }

        return data_get($value, 'verified') === true
            || in_array(data_get($value, 'status'), ['approved', 'verified'], true);
    }

    private function summary(string $name, mixed $value): ?string
    {
        if ($name === 'location' && is_array($value)) {
            $address = data_get($value, 'formatted_address');

            return is_scalar($address) && trim((string) $address) !== ''
                ? mb_substr(trim((string) $address), 0, 255)
                : 'Location captured';
        }

        if ($name === 'kyc' && is_array($value)) {
            $status = data_get($value, 'status');

            return is_scalar($status) ? 'KYC '.ucfirst((string) $status) : 'KYC captured';
        }

        if (in_array($name, ['selfie', 'signature', 'kyc_id_front', 'kyc_id_back'], true)) {
            return ucfirst(str_replace('_', ' ', $name)).' captured';
        }

        if (! is_scalar($value)) {
            return null;
        }

        $summary = trim((string) $value);

        if ($name === 'mobile') {
            $digits = preg_replace('/\D+/', '', $summary);

            return is_string($digits) && strlen($digits) >= 4
                ? '•••• '.substr($digits, -4)
                : 'Mobile captured';
        }

        if ($name === 'email' && str_contains($summary, '@')) {
            [$local, $domain] = explode('@', $summary, 2);

            return mb_substr($local, 0, 1).'•••@'.$domain;
        }

        return $summary === '' ? null : mb_substr($summary, 0, 255);
    }

    /** @return array{disk: string, path: string, mime_type: string, size: int, sha256: string}|null */
    private function storeArtifact(
        Voucher $voucher,
        VoucherClaim $claim,
        string $name,
        mixed $value,
    ): ?array {
        $encoded = $name === 'location' && is_array($value)
            ? data_get($value, 'map')
            : $value;

        if (! in_array($name, ['selfie', 'signature', 'kyc_id_front', 'kyc_id_back', 'location'], true)
            || ! is_string($encoded)
            || trim($encoded) === '') {
            return null;
        }

        $contents = $this->decodeImage($encoded);

        if ($contents === null) {
            throw new RuntimeException(sprintf('Claim evidence [%s] is not a valid image.', $name));
        }

        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents) ?: '';
        $extension = match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw new RuntimeException(sprintf('Claim evidence [%s] has an unsupported image type.', $name)),
        };
        $sha256 = hash('sha256', $contents);
        $disk = (string) config('x-change.claim.evidence.disk', 'local');
        $directory = trim((string) config(
            'x-change.claim.evidence.directory',
            'x-change/claim-evidence',
        ), '/');
        $path = sprintf(
            '%s/%s/%s/%s-%s.%s',
            $directory,
            $voucher->getKey(),
            $claim->getKey(),
            $name,
            $sha256,
            $extension,
        );

        if (! Storage::disk($disk)->put($path, $contents)) {
            throw new RuntimeException(sprintf('Claim evidence [%s] could not be stored.', $name));
        }

        return [
            'disk' => $disk,
            'path' => $path,
            'mime_type' => $mimeType,
            'size' => strlen($contents),
            'sha256' => $sha256,
        ];
    }

    /** @return array<string, mixed>|null */
    private function safePayload(string $name, mixed $value, bool $artifactStored): ?array
    {
        if ($name === 'location' && is_array($value)) {
            return ['value' => Arr::except($value, ['map'])];
        }

        if (in_array($name, ['selfie', 'signature', 'kyc_id_front', 'kyc_id_back'], true)) {
            return $artifactStored ? ['artifact_stored' => true] : null;
        }

        if ($name === 'kyc' && is_array($value)) {
            return ['value' => Arr::except($value, [
                'selfie',
                'id_card_full',
                'id_card_cropped',
                'kyc_id_front',
                'kyc_id_back',
            ])];
        }

        return ['value' => $value];
    }

    private function decodeImage(string $value): ?string
    {
        if (preg_match('/^data:[^;]+;base64,(.+)$/s', $value, $matches) === 1) {
            $value = $matches[1];
        }

        $decoded = base64_decode(preg_replace('/\s+/', '', $value) ?? '', true);

        return is_string($decoded) && $decoded !== '' ? $decoded : null;
    }

    private function manifestHash(VoucherClaim $claim): string
    {
        $manifest = $claim->evidence()
            ->orderBy('requirement_key')
            ->get()
            ->map(static fn (VoucherClaimEvidence $item): array => [
                'key' => $item->requirement_key,
                'kind' => $item->kind->value,
                'status' => $item->status->value,
                'artifact_sha256' => $item->sha256,
            ])
            ->values()
            ->all();

        return hash('sha256', json_encode(
            $manifest,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        ));
    }

    private function attachManifestToSettlementEnvelope(
        Voucher $voucher,
        VoucherClaim $claim,
        string $manifestHash,
    ): void {
        $envelope = $voucher->envelope()->first();

        if ($envelope === null) {
            return;
        }

        EnvelopeSignal::setSignal(
            envelope: $envelope,
            key: 'claim_evidence_manifest_'.$claim->claim_number,
            value: $manifestHash,
            type: 'string',
            source: 'x-change',
        );
        EnvelopeSignal::setSignal(
            envelope: $envelope,
            key: 'claim_evidence_complete_'.$claim->claim_number,
            value: true,
            source: 'x-change',
        );
    }
}
