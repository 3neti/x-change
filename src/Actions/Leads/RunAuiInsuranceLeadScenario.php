<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Leads;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LBHurtado\XChange\Enums\CampaignEntryMode;
use LBHurtado\XChange\Models\LeadCampaign;
use LBHurtado\XChange\Models\PayCodeTemplate;

final readonly class RunAuiInsuranceLeadScenario
{
    private const PREMIUM_MINOR = 5_000;

    private const INSURED_AMOUNT_MINOR = 500_000;

    private const COVERAGE_DURATION_HOURS = 24;

    public function __construct(
        private CreateLeadCampaign $createLeadCampaign,
    ) {}

    public function handle(Model $owner): LeadCampaign
    {
        $suffix = Str::lower(Str::random(6));
        $runReference = 'RUN-AUI-'.Str::upper(Str::random(10));
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
            'settings' => [
                'entry_mode' => CampaignEntryMode::ReusablePaymentQr->value,
                'scenario_run' => [
                    'schema' => 'x-change.lead-campaign-lifecycle-run.v1',
                    'reference' => $runReference,
                    'scenario' => 'aui_on_demand_insurance_payment',
                    'mode' => 'browser_manual_payment',
                    'evidence_classification' => 'application_persisted',
                    'envelope_driver_id' => 'aui.personal-accident.provisional-cover',
                    'envelope_driver_version' => '1.0.0',
                    'product' => [
                        'name' => 'Cubao to Lucena Personal Accident Plan',
                        'premium_minor' => self::PREMIUM_MINOR,
                        'insured_amount_minor' => self::INSURED_AMOUNT_MINOR,
                        'currency' => 'PHP',
                        'coverage_duration_hours' => self::COVERAGE_DURATION_HOURS,
                    ],
                    'started_at' => now()->toIso8601String(),
                ],
            ],
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
            'voucher_type' => 'settlement',
            'target_amount' => self::PREMIUM_MINOR / 100,
            'rules' => [
                'min_payment' => self::PREMIUM_MINOR / 100,
                'max_payment' => self::PREMIUM_MINOR / 100,
                'allow_overpayment' => false,
                'auto_close_on_full_payment' => true,
            ],
            'rider' => [
                'message' => 'Application received. Continue to payment to pay the ₱50.00 insurance premium.',
            ],
            'count' => 1,
            'prefix' => 'AUI',
            'mask' => '****',
            'metadata' => [
                'flow_type' => 'settlement',
                'custom' => [
                    'settlement' => [
                        'driver' => 'claim-intake',
                        'coverage_driver_id' => 'aui.personal-accident.provisional-cover',
                        'coverage_driver_version' => '1.0.0',
                    ],
                    'payment' => [
                        'qr_delivery_modes' => ['payer_page', 'downloadable'],
                    ],
                    'lead_campaign' => [
                        'scenario' => 'aui_on_demand_insurance_payment',
                        'payment_mode' => 'same_code_after_intake',
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
