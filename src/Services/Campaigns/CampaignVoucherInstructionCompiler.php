<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Campaigns;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Enums\VoucherType;
use LBHurtado\XCampaign\Models\CampaignWorksheetAuthorization;
use LBHurtado\XCampaign\Models\CampaignWorksheetFulfillment;
use RuntimeException;

final readonly class CampaignVoucherInstructionCompiler
{
    public function __construct(
        private CampaignVoucherInstructionBlueprintSanitizer $sanitizer,
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

        $instructions = [
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
                'fields' => data_get($blueprint, 'inputs.fields', []),
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
            'validation' => data_get($blueprint, 'validation', []),
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
            'metadata' => [
                'flow_type' => 'campaign_fulfillment',
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
                    ],
                ],
            ],
        ];

        VoucherInstructionsData::createFromAttribs($instructions);

        return $instructions;
    }
}
