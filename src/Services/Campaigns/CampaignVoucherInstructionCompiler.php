<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Campaigns;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Enums\VoucherType;
use LBHurtado\XCampaign\Models\CampaignWorksheetAuthorization;
use LBHurtado\XCampaign\Models\CampaignWorksheetFulfillment;
use LBHurtado\XChange\Services\VoucherIssuancePayloadNormalizer;
use RuntimeException;

final readonly class CampaignVoucherInstructionCompiler
{
    public function __construct(
        private CampaignVoucherInstructionBlueprintSanitizer $sanitizer,
        private VoucherIssuancePayloadNormalizer $normalizer,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function compile(
        CampaignWorksheetAuthorization $authorization,
        CampaignWorksheetFulfillment $fulfillment,
        Model $owner,
    ): array {
        $worksheet = $authorization->worksheet;
        $row = $fulfillment->row;

        if ($worksheet === null || $row === null) {
            throw new RuntimeException('Campaign voucher instructions require an authorized worksheet row.');
        }

        $blueprint = $this->sanitizer->sanitize(
            is_array($authorization->instruction_blueprint_ciphertext)
                ? $authorization->instruction_blueprint_ciphertext
                : [],
        );
        $beneficiary = is_array($row->beneficiary_ciphertext)
            ? $row->beneficiary_ciphertext
            : [];
        $feedbackChannels = data_get($blueprint, 'feedback.channels', []);
        $usesRejectedPayoutClaimRecovery = $fulfillment->mode === 'direct_bank_transfer'
            && (
                data_get($worksheet->metadata, 'lifecycle.failure_disposition') === 'same_pay_code_sms_recovery'
                || data_get($worksheet->metadata, 'lifecycle.schema') === 'x-change.campaign-browser-runner.v1'
            );

        $onboarding = array_key_exists('onboarding', $blueprint)
            ? $blueprint['onboarding'] === true
            : data_get($blueprint, 'claim.onboarding.mode') === 'required';

        $instructions = [
            'onboarding' => $onboarding,
            'cash' => [
                'amount' => $row->amount_minor / 100,
                'currency' => $row->currency,
                'validation' => array_filter([
                    'country' => 'PH',
                    'mobile' => filled($beneficiary['mobile'] ?? null)
                        ? $beneficiary['mobile']
                        : null,
                ]),
            ],
            'inputs' => [
                'fields' => $usesRejectedPayoutClaimRecovery
                    ? ['mobile', 'otp']
                    : data_get($blueprint, 'inputs.fields', []),
            ],
            'feedback' => [
                'email' => in_array('email', $feedbackChannels, true)
                    ? ($beneficiary['email'] ?? null)
                    : null,
                'mobile' => in_array('mobile', $feedbackChannels, true)
                    ? ($beneficiary['mobile'] ?? null)
                    : null,
                'webhook' => null,
            ],
            'rider' => array_replace(
                ['message' => $worksheet->name, 'url' => null],
                data_get($blueprint, 'rider', []),
            ),
            'count' => 1,
            'prefix' => 'CAMP',
            'mask' => '****',
            'voucher_type' => VoucherType::REDEEMABLE->value,
            'validation' => array_replace_recursive(
                data_get($blueprint, 'validation', []),
                $usesRejectedPayoutClaimRecovery
                    ? ['otp' => ['required' => true, 'on_failure' => 'block']]
                    : [],
            ),
            'claim' => [
                'outcomes' => [['key' => 'provider_disbursement']],
                'selection' => 'server',
                'consumption' => 'one_of',
                'default_outcome' => 'provider_disbursement',
                'onboarding' => array_replace(
                    ['mode' => 'if_required'],
                    data_get($blueprint, 'claim.onboarding', []),
                ),
                'claimant' => ['mode' => 'unbound'],
                'profile' => 'voucher.claim.v1',
            ],
            'execution' => $fulfillment->mode === 'direct_bank_transfer'
                ? [
                    'schema' => 'voucher.execution.v1',
                    'driver' => 'x_change_live_cash',
                    'metadata' => [
                        'x_change_live_cash' => [
                            'claim_owner' => 'x-change',
                            'provider' => 'netbank',
                            'settlement_rail' => (string) ($beneficiary['settlement_rail'] ?? 'INSTAPAY'),
                        ],
                    ],
                ]
                : null,
            'metadata' => [
                'flow_type' => 'disbursable',
                'issuer_id' => (string) $owner->getKey(),
                'campaign_id' => (string) $worksheet->reference,
                'campaign_name' => (string) $worksheet->name,
                'source' => 'campaign',
                'custom' => [
                    'campaign' => [
                        'authorization_reference' => $authorization->reference,
                        'fulfillment_reference' => $fulfillment->reference,
                        'manifest_hash' => $authorization->manifest_hash,
                        'instruction_blueprint_hash' => $authorization->instruction_blueprint_hash,
                        'claim_activation' => $usesRejectedPayoutClaimRecovery
                            ? 'provider_rejection'
                            : 'immediate',
                    ],
                ],
            ],
        ];

        $instructions = $this->normalizer->normalize($instructions);
        VoucherInstructionsData::createFromAttribs($instructions);

        return $instructions;
    }
}
