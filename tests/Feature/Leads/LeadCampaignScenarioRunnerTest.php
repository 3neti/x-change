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

it('renders the disbursable feedback endpoint as the default browser scenario', function (): void {
    actingAsTestUser();

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.campaigns.lead-scenario-runner.show'))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/cockpit/LeadCampaignScenarioRunner')
        ->assertJsonPath('props.scenario.key', 'disbursable_feedback_endpoint')
        ->assertJsonPath('props.scenario.title', '₱25 Disbursable Feedback Endpoint')
        ->assertJsonPath('props.scenario.amount', '₱25.00')
        ->assertJsonPath('props.scenario.claim_surface', '/x/claim/{code}')
        ->assertJsonPath('props.scenario.fields.0', 'Disbursable')
        ->assertJsonPath('props.scenarios.1.key', 'aui_on_demand_insurance_payment')
        ->assertJsonPath('props.recent_lead_campaigns', []);
});

it('runs the disbursable feedback scenario with a bounded endpoint template', function (): void {
    $operator = actingAsTestUser();

    $this->post(route('x-change.cockpit.campaigns.lead-scenario-runner.store'), [
        'scenario' => 'disbursable_feedback_endpoint',
    ])
        ->assertRedirect();

    $campaign = LeadCampaign::query()->sole();
    $template = PayCodeTemplate::query()->sole();

    expect($campaign->title)->toBe('₱25 Feedback Endpoint')
        ->and($campaign->owner_type)->toBe($operator->getMorphClass())
        ->and($campaign->owner_id)->toBe((string) $operator->getKey())
        ->and($campaign->endpoint_slug)->toStartWith('feedback-endpoint-')
        ->and($campaign->starts_limit)->toBe(1)
        ->and($campaign->expires_at)->not->toBeNull()
        ->and($campaign->settings)->toMatchArray([
            'kind' => 'lead',
            'entry_point' => 'public_qr_link',
            'person_type' => 'prospect',
            'pay_code_generation' => 'on_scan',
            'usage_key' => 'custom',
            'capabilities' => ['distribution', 'feedback'],
            'limits' => [
                'budget_cap_minor' => 2_500,
                'claims_limit' => 1,
                'per_identity_claim_limit' => 1,
            ],
        ])
        ->and($template->name)->toBe('₱25 Feedback Endpoint')
        ->and(data_get($template->instructions_ciphertext, 'cash.amount'))->toBe(25)
        ->and(data_get($template->instructions_ciphertext, 'metadata.flow_type'))->toBe('disbursable')
        ->and(data_get($template->instructions_ciphertext, 'inputs.fields'))->toBe([])
        ->and(data_get($template->instructions_ciphertext, 'feedback.mobile'))->toBe('09173011987')
        ->and(data_get($template->instructions_ciphertext, 'rider.message'))->toBe('test feedback')
        ->and(data_get($template->instructions_ciphertext, 'metadata.custom.lead_campaign.scenario'))->toBe('disbursable_feedback_endpoint');
});

it('keeps the AUI browser scenario executable against voucher input fields', function (): void {
    actingAsTestUser();

    $this->post(route('x-change.cockpit.campaigns.lead-scenario-runner.store'), [
        'scenario' => 'aui_on_demand_insurance_payment',
    ])
        ->assertRedirect();

    $template = PayCodeTemplate::query()->sole();
    $supported = VoucherInputField::values();

    expect(data_get($template->instructions_ciphertext, 'inputs.fields'))
        ->each->toBeIn($supported);
});

it('continues the feedback browser scenario through public endpoint generation into claim', function (): void {
    actingAsTestUser();

    $fakeIssuer = auiLeadCampaignFakeGeneratePayCode('AUI-LIFE-1');

    app()->instance(GeneratePayCode::class, $fakeIssuer);

    $this->post(route('x-change.cockpit.campaigns.lead-scenario-runner.store'), [
        'scenario' => 'disbursable_feedback_endpoint',
    ])
        ->assertRedirect();

    $campaign = LeadCampaign::query()->sole();

    $this->get(route('x-change.leads.start', [
        'merchant_slug' => $campaign->merchant_slug,
        'endpoint_slug' => $campaign->endpoint_slug,
    ]))->assertRedirect(route('x-change.claim.show', ['code' => 'AUI-LIFE-1']));

    expect($campaign->fresh()->usage_count)->toBe(1)
        ->and(data_get($fakeIssuer->payloads[0], 'cash.amount'))->toBe(25)
        ->and(data_get($fakeIssuer->payloads[0], 'feedback.mobile'))->toBe('09173011987')
        ->and(data_get($fakeIssuer->payloads[0], 'rider.message'))->toBe('test feedback')
        ->and(data_get($fakeIssuer->payloads[0], 'metadata.custom.lead_campaign.scenario'))->toBe('disbursable_feedback_endpoint')
        ->and(data_get($fakeIssuer->payloads[0], 'metadata.custom.lead_campaign.campaign_reference'))->toBe($campaign->reference)
        ->and(data_get($fakeIssuer->payloads[0], 'metadata.flow_type'))->toBe('disbursable');
});

it('rejects unknown browser scenarios before creating campaign records', function (): void {
    actingAsTestUser();

    $this->post(route('x-change.cockpit.campaigns.lead-scenario-runner.store'), [
        'scenario' => 'unknown',
    ])->assertSessionHasErrors('scenario');

    expect(LeadCampaign::query()->count())->toBe(0)
        ->and(PayCodeTemplate::query()->count())->toBe(0);
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
