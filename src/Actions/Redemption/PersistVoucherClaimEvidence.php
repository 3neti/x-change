<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Redemption;

use Illuminate\Database\Eloquent\Model;
use JsonException;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Enums\ClaimEvidenceKind;
use LBHurtado\XChange\Enums\ClaimEvidenceStatus;
use LBHurtado\XChange\Models\VoucherClaim;
use LBHurtado\XChange\Models\VoucherClaimEvidence;

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

            /** @var Model $input */
            $input = $voucher->inputs()->create([
                'name' => $normalizedName,
                'value' => $normalizedValue,
            ]);

            $inputIds[] = (int) $input->getKey();

            $kind = $this->kind($normalizedName);
            $verified = $this->isVerified($normalizedName, $value);
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
                'payload' => ['value' => $value],
                'captured_at' => now(),
                'verified_at' => $verified ? now() : null,
                'metadata' => [
                    'manifest_version' => 1,
                    'legacy_input_id' => $input->getKey(),
                ],
            ]);
            $evidenceIds[] = (int) $evidenceRecord->getKey();
        }

        if ($inputIds !== []) {
            $meta = (array) $claim->meta;
            data_set($meta, 'evidence.input_ids', $inputIds);
            data_set($meta, 'evidence.record_ids', $evidenceIds);
            data_set($meta, 'evidence.manifest_version', 1);
            data_set($meta, 'evidence.persisted', true);
            $claim->forceFill(['meta' => $meta])->save();
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

        return $summary === '' ? null : mb_substr($summary, 0, 255);
    }
}
