<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Campaigns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Disbursement\RefurbishRejectedPayCodePayout;
use LBHurtado\XChange\Data\Redemption\SubmitPayCodeClaimResultData;
use LBHurtado\XChange\Services\Claim\ClaimEvidenceRequirements;
use LBHurtado\XChange\Support\Auth\MobileNumber;
use RuntimeException;

final readonly class SubmitCampaignPayoutRecoveryClaim
{
    public function __construct(
        private RefurbishRejectedPayCodePayout $payouts,
        private ClaimEvidenceRequirements $evidence,
    ) {}

    /** @param array<string, mixed> $payload */
    public function handle(Voucher $voucher, array $payload): SubmitPayCodeClaimResultData
    {
        $owner = $voucher->owner;
        if (! $owner instanceof Model) {
            throw new RuntimeException('Campaign payout recovery authority is unavailable.');
        }

        $this->evidence->assertComplete($voucher, $payload);
        $mobile = $this->verifiedBeneficiaryMobile($voucher, $payload);
        $bankCode = $this->requiredString($payload, 'bank_code');
        $accountNumber = $this->requiredString($payload, 'account_number');

        $result = $this->payouts->handle(
            voucher: $voucher,
            requestedBy: $owner,
            bankCode: $bankCode,
            accountNumber: $accountNumber,
            mobile: $mobile,
        );
        $status = (string) data_get($result, 'status', 'unknown');
        $succeeded = $status === 'succeeded';
        $amount = (float) data_get($voucher->metadata, 'instructions.cash.amount', 0);
        $currency = (string) data_get($voucher->metadata, 'instructions.cash.currency', 'PHP');

        return new SubmitPayCodeClaimResultData(
            voucher_code: (string) $voucher->code,
            claim_type: 'payout_recovery',
            claimed: $succeeded,
            status: $status,
            requested_amount: $amount,
            disbursed_amount: $succeeded ? $amount : null,
            currency: $currency,
            remaining_balance: $succeeded ? 0.0 : $amount,
            fully_claimed: $succeeded,
            disbursement: [
                'status' => $status,
                'provider_reference' => data_get($result, 'provider_reference'),
                'provider_transaction_id' => data_get($result, 'provider_transaction_id'),
                'destination_revision' => data_get($result, 'destination_revision'),
            ],
            messages: [$succeeded
                ? 'Your protected Pay Code was sent to the corrected destination.'
                : 'Your corrected payout was submitted and is awaiting confirmation.'],
        );
    }

    /** @param array<string, mixed> $payload */
    private function verifiedBeneficiaryMobile(Voucher $voucher, array $payload): string
    {
        $expected = MobileNumber::normalize(data_get(
            $voucher->metadata,
            'instructions.cash.validation.mobile',
        ));
        $submitted = MobileNumber::normalize(data_get($payload, 'mobile'));
        $proofMobile = MobileNumber::normalize(data_get($payload, 'inputs.otp.mobile'));

        if ($expected === null || $submitted === null || $proofMobile === null
            || ! hash_equals($expected, $submitted)
            || ! hash_equals($expected, $proofMobile)) {
            throw ValidationException::withMessages([
                'mobile' => 'Use the beneficiary mobile number that received this Pay Code.',
            ]);
        }

        return '+'.$expected;
    }

    /** @param array<string, mixed> $payload */
    private function requiredString(array $payload, string $key): string
    {
        $value = data_get($payload, $key);
        if (! is_scalar($value) || trim((string) $value) === '') {
            throw ValidationException::withMessages([
                $key => 'A payout destination is required.',
            ]);
        }

        return trim((string) $value);
    }
}
