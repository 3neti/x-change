<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Campaigns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Campaigns\ApproveCampaignWorksheetAuthorization;
use LBHurtado\XChange\Contracts\SettlementExecutionContract;
use LBHurtado\XChange\Data\Settlement\SettlementExecutionResultData;
use RuntimeException;

final readonly class CampaignWorksheetAuthorizationExecutionService implements SettlementExecutionContract
{
    public function __construct(
        private ApproveCampaignWorksheetAuthorization $authorizations,
    ) {}

    public function execute(Voucher $voucher, array $payload): SettlementExecutionResultData
    {
        $officer = Auth::user();
        if (! $officer instanceof Model) {
            throw new RuntimeException('An authenticated officer is required to approve a campaign worksheet.');
        }

        $this->assertOfficerMobileMatchesClaim($officer, $payload);
        $this->assertRequiredOtp($voucher, $payload);

        $authorization = $this->authorizations->handle((string) $voucher->code, $officer);

        return new SettlementExecutionResultData(
            voucher_code: (string) $voucher->code,
            status: 'authorized',
            message: 'Campaign worksheet authorized. Beneficiary issuance has not started.',
            meta: [
                'authorization_reference' => $authorization->reference,
                'worksheet_reference' => $authorization->worksheet?->reference,
                'beneficiary_count' => $authorization->beneficiary_count,
                'principal_minor' => $authorization->principal_minor,
                'currency' => $authorization->currency,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertOfficerMobileMatchesClaim(Model $officer, array $payload): void
    {
        $claimMobile = $this->normalizeMobile(data_get($payload, 'mobile'));
        $officerMobile = $this->normalizeMobile($officer->getAttribute('mobile'));

        if ($claimMobile === null || $officerMobile === null || ! hash_equals($officerMobile, $claimMobile)) {
            throw new RuntimeException('The verified mobile number must belong to the authenticated officer.');
        }
    }

    private function normalizeMobile(mixed $mobile): ?string
    {
        if (! is_string($mobile) || trim($mobile) === '') {
            return null;
        }

        try {
            return phone($mobile, 'PH')->formatE164();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertRequiredOtp(Voucher $voucher, array $payload): void
    {
        if (
            data_get($voucher->instructions, 'validation.otp.required') === true
            && data_get($payload, 'inputs.otp.verified') !== true
        ) {
            throw new RuntimeException('A verified OTP is required to authorize this campaign.');
        }
    }
}
