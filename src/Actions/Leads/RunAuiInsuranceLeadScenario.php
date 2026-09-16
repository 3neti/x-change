<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Leads;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LBHurtado\XChange\Models\LeadCampaign;
use LBHurtado\XChange\Models\PayCodeTemplate;

final readonly class RunAuiInsuranceLeadScenario
{
    public function __construct(
        private CreateLeadCampaign $createLeadCampaign,
    ) {}

    public function handle(Model $owner): LeadCampaign
    {
        $suffix = Str::lower(Str::random(6));
        $template = PayCodeTemplate::query()->create([
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => (string) $owner->getKey(),
            'name' => 'AUI On-Demand Insurance Payment',
            'description' => 'Browser lifecycle scenario: prospect intake before issuing the insurance payment instructions.',
            'base_template_key' => 'blank-pay-code',
            'instructions_ciphertext' => $this->instructions(),
            'include_amount' => true,
            'include_purpose' => true,
            'status' => 'active',
        ]);

        return $this->createLeadCampaign->handle($owner, $template, [
            'title' => 'AUI On-Demand Insurance Payment',
            'endpoint_slug' => 'aui-on-demand-insurance-payment-'.$suffix,
            'description' => 'Public QR/link intake for a prospect who wants insurance payment instructions.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function instructions(): array
    {
        return [
            'cash' => [
                'amount' => 0,
                'currency' => 'PHP',
                'validation' => [
                    'country' => 'PH',
                ],
            ],
            'inputs' => [
                'fields' => [
                    'name',
                    'mobile',
                    'email',
                    'address',
                    'birth_date',
                    'reference_code',
                ],
            ],
            'feedback' => [],
            'rider' => [
                'message' => 'AUI insurance payment intake',
            ],
            'count' => 1,
            'prefix' => 'AUI',
            'mask' => '****',
            'metadata' => [
                'custom' => [
                    'lead_campaign' => [
                        'scenario' => 'aui_on_demand_insurance_payment',
                        'payment_mode' => 'invoice_after_intake',
                        'invoice_channels' => ['email', 'downloadable_qrph'],
                        'requested_particulars' => [
                            'insurance_product',
                            'vehicle_registration_number',
                            'driver_license_number',
                            'payment_reference',
                        ],
                    ],
                ],
            ],
            'claim' => [
                'outcomes' => [
                    ['key' => 'lead_intake'],
                ],
                'default_outcome' => 'lead_intake',
            ],
        ];
    }
}
