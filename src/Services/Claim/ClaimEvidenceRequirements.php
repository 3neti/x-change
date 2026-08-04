<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Claim;

use BackedEnum;
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
        $requirements = $this->normalizeRequirements(
            data_get($issuance, 'inputs.fields', []),
        );

        data_set($issuance, 'metadata.claim_evidence', [
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
        $instructions = is_array($voucher->metadata)
            ? (array) data_get($voucher->metadata, 'instructions', [])
            : [];
        $snapshot = data_get($instructions, 'metadata.claim_evidence.requirements');

        return $this->normalizeRequirements(
            is_array($snapshot)
                ? $snapshot
                : data_get($instructions, 'inputs.fields', []),
        );
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
                || data_get($inputs, 'otp_verified') === true;
        }

        if ($requirement === 'location') {
            return is_array($value)
                && is_numeric(data_get($value, 'latitude'))
                && is_numeric(data_get($value, 'longitude'));
        }

        if ($requirement === 'kyc') {
            return is_array($value)
                && in_array(data_get($value, 'status'), ['approved', 'verified'], true);
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        return $value !== null && $value !== [];
    }
}
