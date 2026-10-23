<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Claim;

use BackedEnum;
use Illuminate\Contracts\Support\Arrayable;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Exceptions\IncompleteClaimEvidence;

final class ClaimEvidenceRequirements
{
    /**
     * @param  array<string, mixed>  $issuance
     * @return array<string, mixed>
     */
    public function snapshot(array $issuance): array
    {
        $requirements = array_values(array_unique([
            ...$this->normalizeRequirements(data_get($issuance, 'inputs.fields', [])),
            ...$this->blockingValidationRequirements($issuance),
        ]));

        data_set($issuance, 'metadata.custom.claim_evidence', [
            'manifest_version' => 1,
            'requirements' => $requirements,
            'required_count' => count($requirements),
        ]);

        return $issuance;
    }

    /** @param array<string, mixed> $payload */
    public function assertComplete(Voucher $voucher, array $payload): void
    {
        $requirements = $this->forVoucher($voucher);

        if ($requirements === []) {
            return;
        }

        $inputs = (array) data_get($payload, 'inputs', []);
        $missing = array_values(array_filter(
            $requirements,
            fn (string $requirement): bool => ! $this->isSatisfied(
                $requirement,
                $inputs[$requirement] ?? data_get($payload, $requirement),
                $inputs,
            ),
        ));

        if ($missing !== []) {
            throw new IncompleteClaimEvidence($missing);
        }
    }

    /** @return list<string> */
    public function forVoucher(Voucher $voucher): array
    {
        $instructions = $voucher->getRawOriginal('instructions')
            ?: data_get($voucher->metadata, 'instructions', []);
        if (is_string($instructions)) {
            $instructions = json_decode($instructions, true);
        } elseif ($instructions instanceof Arrayable) {
            $instructions = $instructions->toArray();
        }
        if (! is_array($instructions)) {
            $instructions = [];
        }
        $snapshot = data_get($instructions, 'metadata.custom.claim_evidence.requirements')
            ?? data_get($instructions, 'rules.claim_evidence.requirements')
            ?? data_get($instructions, 'metadata.claim_evidence.requirements');

        $requirements = $this->normalizeRequirements(
            is_array($snapshot)
                ? $snapshot
                : data_get($instructions, 'inputs.fields', []),
        );

        return array_values(array_unique([
            ...$requirements,
            ...$this->blockingValidationRequirements($instructions),
        ]));
    }

    /** @param array<string, mixed> $instructions
     * @return list<string>
     */
    private function blockingValidationRequirements(array $instructions): array
    {
        $requirements = [];

        foreach (['otp' => 'otp', 'face_match' => 'kyc', 'location' => 'location'] as $rule => $requirement) {
            if (data_get($instructions, "validation.{$rule}.required") === true
                && data_get($instructions, "validation.{$rule}.on_failure", 'block') !== 'warn') {
                $requirements[] = $requirement;
            }
        }

        return $requirements;
    }

    /** @return list<string> */
    private function normalizeRequirements(mixed $requirements): array
    {
        if (! is_array($requirements)) {
            return [];
        }

        return collect($requirements)
            ->map(static function (mixed $requirement): string {
                if ($requirement instanceof BackedEnum) {
                    $requirement = $requirement->value;
                }

                return is_scalar($requirement) ? trim((string) $requirement) : '';
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** @param array<string, mixed> $inputs */
    private function isSatisfied(string $requirement, mixed $value, array $inputs): bool
    {
        if ($requirement === 'otp') {
            return data_get($inputs, 'otp.verified') === true
                && filled(data_get($inputs, 'otp.verification_reference'))
                && filled(data_get($inputs, 'otp.verification_purpose'))
                && filled(data_get($inputs, 'otp.verified_at'));
        }

        if ($requirement === 'location') {
            return is_array($value)
                && is_numeric(data_get($value, 'latitude'))
                && is_numeric(data_get($value, 'longitude'))
                && abs((float) data_get($value, 'latitude')) <= 90
                && abs((float) data_get($value, 'longitude')) <= 180;
        }

        if ($requirement === 'kyc') {
            return is_array($value)
                && in_array(data_get($value, 'status'), ['approved', 'verified'], true)
                && filled(data_get($value, 'transaction_id'));
        }

        if (in_array($requirement, ['selfie', 'signature', 'kyc_id_front', 'kyc_id_back'], true)) {
            return $this->isSupportedImage($value);
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        return $value !== null && $value !== [];
    }

    private function isSupportedImage(mixed $value): bool
    {
        if (! is_string($value) || trim($value) === '') {
            return false;
        }

        $encoded = preg_replace('/^data:[^;]+;base64,/i', '', trim($value));
        if (! is_string($encoded) || strlen($encoded) > 14_000_000) {
            return false;
        }

        $contents = base64_decode(preg_replace('/\s+/', '', $encoded) ?? '', true);
        if (! is_string($contents) || strlen($contents) > 10_000_000) {
            return false;
        }

        $dimensions = @getimagesizefromstring($contents);

        return is_array($dimensions)
            && in_array($dimensions['mime'] ?? null, ['image/jpeg', 'image/png', 'image/webp'], true)
            && ($dimensions[0] ?? 0) > 0
            && ($dimensions[1] ?? 0) > 0
            && ($dimensions[0] ?? 0) <= 8192
            && ($dimensions[1] ?? 0) <= 8192;
    }
}
