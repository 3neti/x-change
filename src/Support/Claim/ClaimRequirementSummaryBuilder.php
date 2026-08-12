<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Support\Claim;

use Illuminate\Support\Arr;
use LBHurtado\Voucher\Enums\VoucherInputField;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Enums\ClaimEvidenceStatus;
use LBHurtado\XChange\Models\VoucherClaim;

/**
 * Produces `claim_requirement_summary` items: status only, never a raw
 * value. Evidence rows already hide `payload`/`artifact_path`/`sha256`
 * (see `VoucherClaimEvidence::$hidden`); this builder additionally never
 * reads or forwards `summary` for capture-style requirements (selfie,
 * location, signature, KYC, name) so a redacted-looking string can never
 * leak into the issuer console. Destination account status is read from
 * the claim's own already-masked `bank_code`/`account_number_masked`
 * columns, never from evidence.
 */
final class ClaimRequirementSummaryBuilder
{
    private const LABELS = [
        'mobile' => 'Mobile number',
        'destination_account' => 'Destination account',
        'selfie' => 'Selfie',
        'location' => 'Location',
        'signature' => 'Signature',
        'kyc' => 'KYC',
        'name' => 'Name',
        'secret' => 'Passcode',
        'otp' => 'OTP',
        'approval' => 'Approval',
    ];

    /**
     * @return array<int, array{key: string, label: string, status: string, tone: string, description: null}>
     */
    public function build(Voucher $voucher, ?VoucherClaim $claim, bool $approvalRequired): array
    {
        $configured = $this->configuredKeys($voucher);
        $items = [];

        // Every redeemable claim collects a payout destination, even though
        // `bank_code`/`account_number` are never valid `inputs.fields`
        // values (they aren't part of the `VoucherInputField` enum) -- so
        // this row is unconditional rather than gated on configuration.
        $items[] = $this->item(
            'destination_account',
            $this->destinationAccountStatus($claim),
        );

        foreach (['mobile', 'name'] as $key) {
            if (! in_array($key, $configured, true)) {
                continue;
            }

            $items[] = $this->item($key, $this->simpleConfirmationStatus($claim, $key));
        }

        foreach (['selfie', 'location', 'signature', 'kyc'] as $key) {
            if (! in_array($key, $configured, true)) {
                continue;
            }

            $items[] = $this->item($key, $this->captureStatus($claim, $key));
        }

        if (in_array('secret', $configured, true)) {
            $items[] = $this->item('secret', $claim !== null ? 'verified' : 'pending');
        }

        if ($this->hasOtpEvidence($claim)) {
            $items[] = $this->item('otp', $this->otpStatus($claim));
        }

        if ($approvalRequired) {
            $items[] = $this->item('approval', 'pending');
        }

        return $items;
    }

    /**
     * @return array{key: string, label: string, status: string, tone: string, description: null}
     */
    private function item(string $key, string $status): array
    {
        return [
            'key' => $key,
            'label' => self::LABELS[$key] ?? $key,
            'status' => $status,
            'tone' => $this->tone($status),
            'description' => null,
        ];
    }

    private function tone(string $status): string
    {
        return match ($status) {
            'completed', 'captured', 'verified', 'approved', 'provided' => 'positive',
            'pending' => 'warning',
            'failed' => 'critical',
            default => 'neutral',
        };
    }

    /**
     * @return list<string>
     */
    private function configuredKeys(Voucher $voucher): array
    {
        $instructions = $this->instructionsArray($voucher);
        $keys = array_values(array_filter(array_map(
            static fn (mixed $field): ?string => match (true) {
                $field instanceof VoucherInputField => $field->value,
                is_string($field) => $field,
                default => null,
            },
            Arr::wrap(data_get($instructions, 'inputs.fields', [])),
        )));

        if (filled(data_get($instructions, 'cash.validation.secret'))) {
            $keys[] = 'secret';
        }

        return array_values(array_unique($keys));
    }

    /**
     * @return array<string, mixed>
     */
    private function instructionsArray(Voucher $voucher): array
    {
        try {
            return (array) $voucher->instructions->toArray();
        } catch (\Throwable) {
            return [];
        }
    }

    private function simpleConfirmationStatus(?VoucherClaim $claim, string $requirementKey): string
    {
        if ($claim === null) {
            return 'pending';
        }

        $evidence = $claim->evidence->firstWhere('requirement_key', $requirementKey);

        if ($evidence === null) {
            return 'missing';
        }

        return $requirementKey === 'mobile' ? 'completed' : 'provided';
    }

    private function destinationAccountStatus(?VoucherClaim $claim): string
    {
        if ($claim === null) {
            return 'pending';
        }

        return filled($claim->bank_code) && filled($claim->account_number_masked)
            ? 'completed'
            : 'missing';
    }

    private function captureStatus(?VoucherClaim $claim, string $requirementKey): string
    {
        if ($claim === null) {
            return 'pending';
        }

        $evidence = $claim->evidence->firstWhere('requirement_key', $requirementKey);

        if ($evidence === null) {
            return 'missing';
        }

        return match ($evidence->status) {
            ClaimEvidenceStatus::Verified => $requirementKey === 'kyc' ? 'approved' : 'verified',
            ClaimEvidenceStatus::Failed => 'failed',
            ClaimEvidenceStatus::NotRetained => 'provided',
            ClaimEvidenceStatus::Missing => 'missing',
            ClaimEvidenceStatus::Captured => 'captured',
        };
    }

    private function hasOtpEvidence(?VoucherClaim $claim): bool
    {
        if ($claim === null) {
            return false;
        }

        return $claim->evidence->contains(
            static fn ($evidence): bool => in_array($evidence->requirement_key, ['otp', 'otp_verified'], true),
        );
    }

    private function otpStatus(?VoucherClaim $claim): string
    {
        if ($claim === null) {
            return 'pending';
        }

        $verified = $claim->evidence
            ->whereIn('requirement_key', ['otp', 'otp_verified'])
            ->contains(static fn ($evidence): bool => $evidence->status === ClaimEvidenceStatus::Verified);

        return $verified ? 'approved' : 'captured';
    }
}
