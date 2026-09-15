<?php

declare(strict_types=1);

use LBHurtado\Voucher\Enums\VoucherInputField;
use LBHurtado\XChange\Actions\PayCode\GeneratePayCode;
use LBHurtado\XChange\Data\DebitData;
use LBHurtado\XChange\Data\IssuerData;
use LBHurtado\XChange\Data\PayCode\GeneratePayCodeResultData;
use LBHurtado\XChange\Data\PayCodeLinksData;
use LBHurtado\XChange\Data\PricingEstimateData;
use LBHurtado\XChange\Models\LeadCampaign;
use LBHurtado\XChange\Models\PayCodeTemplate;

it('renders the browser based AUI lead campaign scenario runner', function (): void {
    actingAsTestUser();

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.campaigns.lead-scenario-runner.show'))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/cockpit/LeadCampaignScenarioRunner')
        ->assertJsonPath('props.scenario.key', 'aui_on_demand_insurance_payment')
        ->assertJsonPath('props.scenario.title', 'AUI On-Demand Insurance Payment')
        ->assertJsonPath('props.scenario.amount', '₱0.00')
        ->assertJsonPath('props.scenario.claim_surface', '/x/claim/{code}')
        ->assertJsonPath('props.scenario.fields.0', 'Name')
        ->assertJsonPath('props.recent_lead_campaigns', []);
});

it('runs the AUI browser scenario and redirects through the public lead endpoint', function (): void {
    $operator = actingAsTestUser();

    $this->post(route('x-change.cockpit.campaigns.lead-scenario-runner.store'))
        ->assertRedirect();

    $campaign = LeadCampaign::query()->sole();
    $template = PayCodeTemplate::query()->sole();

    expect($campaign->title)->toBe('AUI On-Demand Insurance Payment')
        ->and($campaign->owner_type)->toBe($operator->getMorphClass())
        ->and($campaign->owner_id)->toBe((string) $operator->getKey())
        ->and($campaign->endpoint_slug)->toStartWith('aui-on-demand-insurance-payment-')
        ->and($campaign->settings)->toMatchArray([
            'kind' => 'lead',
            'entry_point' => 'public_qr_link',
            'person_type' => 'prospect',
            'pay_code_generation' => 'on_scan',
        ])
        ->and($template->name)->toBe('AUI On-Demand Insurance Payment')
        ->and(data_get($template->instructions_ciphertext, 'cash.amount'))->toBe(0)
        ->and(data_get($template->instructions_ciphertext, 'inputs.fields'))->toBe([
            'name',
            'mobile',
            'email',
            'address',
            'birth_date',
            'reference_code',
        ])
        ->and(data_get($template->instructions_ciphertext, 'metadata.custom.lead_campaign.scenario'))->toBe('aui_on_demand_insurance_payment')
        ->and(data_get($template->instructions_ciphertext, 'metadata.custom.lead_campaign.payment_mode'))->toBe('invoice_after_intake')
        ->and(data_get($template->instructions_ciphertext, 'metadata.custom.lead_campaign.requested_particulars'))->toBe([
            'insurance_product',
            'vehicle_registration_number',
            'driver_license_number',
            'payment_reference',
        ]);
});

it('keeps the AUI browser scenario executable against voucher input fields', function (): void {
    actingAsTestUser();

    $this->post(route('x-change.cockpit.campaigns.lead-scenario-runner.store'))
        ->assertRedirect();

    $template = PayCodeTemplate::query()->sole();
    $supported = VoucherInputField::values();

    expect(data_get($template->instructions_ciphertext, 'inputs.fields'))
        ->each->toBeIn($supported);
});

it('continues the browser scenario through public endpoint generation into claim', function (): void {
    actingAsTestUser();

    $fakeIssuer = auiLeadCampaignFakeGeneratePayCode('AUI-LIFE-1');

    app()->instance(GeneratePayCode::class, $fakeIssuer);

    $this->post(route('x-change.cockpit.campaigns.lead-scenario-runner.store'))
        ->assertRedirect();

    $campaign = LeadCampaign::query()->sole();

    $this->get(route('x-change.leads.start', [
        'merchant_slug' => $campaign->merchant_slug,
        'endpoint_slug' => $campaign->endpoint_slug,
    ]))->assertRedirect(route('x-change.claim.show', ['code' => 'AUI-LIFE-1']));

    expect($campaign->fresh()->usage_count)->toBe(1)
        ->and(data_get($fakeIssuer->payloads[0], 'metadata.custom.lead_campaign.scenario'))->toBe('aui_on_demand_insurance_payment')
        ->and(data_get($fakeIssuer->payloads[0], 'metadata.custom.lead_campaign.payment_mode'))->toBe('invoice_after_intake')
        ->and(data_get($fakeIssuer->payloads[0], 'metadata.custom.lead_campaign.campaign_reference'))->toBe($campaign->reference);
});

function auiLeadCampaignFakeGeneratePayCode(string $code): GeneratePayCode
{
    return new class($code) extends GeneratePayCode
    {
        /**
         * @var array<int, array<string, mixed>>
         */
        public array $payloads = [];

        public function __construct(private readonly string $code) {}

        /**
         * @param  array<string, mixed>  $input
         */
        public function handle(array $input): GeneratePayCodeResultData
        {
            $this->payloads[] = $input;

            return new GeneratePayCodeResultData(
                voucher_id: 99002,
                code: $this->code,
                amount: data_get($input, 'cash.amount', 0),
                currency: (string) data_get($input, 'cash.currency', 'PHP'),
                issuer: new IssuerData(id: data_get($input, 'metadata.issuer_id')),
                cost: new PricingEstimateData(currency: 'PHP', total: 0),
                wallet: [],
                debit: new DebitData,
                links: new PayCodeLinksData(
                    redeem: 'https://example.test/x/claim/'.$this->code,
                    redeem_path: '/x/claim/'.$this->code,
                ),
            );
        }
    };
}
