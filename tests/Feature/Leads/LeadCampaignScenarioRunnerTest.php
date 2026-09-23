<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use LBHurtado\EmiCore\Data\Funding\ProviderFundingObservationData;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Enums\VoucherInputField;
use LBHurtado\XChange\Actions\PayCode\GeneratePayCode;
use LBHurtado\XChange\Actions\Payment\RecognizeSettlementVoucherCollection;
use LBHurtado\XChange\Actions\Payment\VerifyPaymentAttempt;
use LBHurtado\XChange\Actions\Redemption\SubmitWebPayCodeClaim;
use LBHurtado\XChange\Data\DebitData;
use LBHurtado\XChange\Data\IssuerData;
use LBHurtado\XChange\Data\PayCode\GeneratePayCodeResultData;
use LBHurtado\XChange\Data\PayCodeLinksData;
use LBHurtado\XChange\Data\PricingEstimateData;
use LBHurtado\XChange\Enums\PaymentAttemptStatus;
use LBHurtado\XChange\Enums\PaymentVerificationTrigger;
use LBHurtado\XChange\Models\CampaignPaymentRecognition;
use LBHurtado\XChange\Models\CampaignPaymentSource;
use LBHurtado\XChange\Models\LeadCampaign;
use LBHurtado\XChange\Models\PayCodeTemplate;
use LBHurtado\XChange\Models\PaymentAttempt;
use LBHurtado\XChange\Models\VoucherCollection;
use LBHurtado\XChange\Services\Funding\FundingProviderAdapterRegistry;
use LBHurtado\XChange\Tests\Fakes\FakeFundingProviderAdapter;

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
        ->assertJsonPath('props.scenarios.1.amount', '₱0.00 disbursement · ₱100.00 collection target')
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
    $typedInstructions = VoucherInstructionsData::from($template->instructions_ciphertext);

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
        ->and(data_get($template->instructions_ciphertext, 'claim.outcomes.0.key'))->toBe('provider_disbursement')
        ->and(data_get($template->instructions_ciphertext, 'claim.default_outcome'))->toBe('provider_disbursement')
        ->and(data_get($template->instructions_ciphertext, 'claim.profile'))->toBe('voucher.claim.v1')
        ->and($typedInstructions->claim?->outcomes[0]->key)->toBe('provider_disbursement')
        ->and(data_get($template->instructions_ciphertext, 'metadata.custom.lead_campaign.scenario'))->toBe('disbursable_feedback_endpoint');
});

it('keeps the AUI browser scenario executable against voucher input fields', function (): void {
    $operator = actingAsTestUser();

    $response = $this->post(route('x-change.cockpit.campaigns.lead-scenario-runner.store'), [
        'scenario' => 'aui_on_demand_insurance_payment',
    ]);

    $template = PayCodeTemplate::query()->sole();
    $campaign = LeadCampaign::query()->sole();
    $supported = VoucherInputField::values();

    $response->assertRedirect(route(
        'x-change.cockpit.campaigns.lead-scenario-runner.runs.show',
        ['campaign' => $campaign->reference],
    ));

    expect(data_get($template->instructions_ciphertext, 'inputs.fields'))
        ->each->toBeIn($supported)
        ->and(data_get(
            $template->instructions_ciphertext,
            'metadata.custom.payment.qr_delivery_modes',
        ))->toBe(['payer_page', 'downloadable'])
        ->and(data_get(
            $template->instructions_ciphertext,
            'metadata.custom.lead_campaign.invoice_channels',
        ))->toBeNull()
        ->and(data_get($template->instructions_ciphertext, 'metadata.custom.settlement.coverage_driver_id'))
        ->toBe('aui.personal-accident.provisional-cover')
        ->and(data_get($campaign->settings, 'scenario_run.schema'))
        ->toBe('x-change.lead-campaign-lifecycle-run.v1');

    $this->actingAs($operator)
        ->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.campaigns.lead-scenario-runner.runs.show', [
            'campaign' => $campaign->reference,
        ]))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/cockpit/LeadCampaignLifecycleScenarioRun')
        ->assertJsonPath('props.run.status', 'running')
        ->assertJsonPath('props.run.declarations.driver_authority', 'demonstration_only')
        ->assertJsonPath('props.run.steps.0.status', 'passed')
        ->assertJsonPath('props.run.steps.2.status', 'passed')
        ->assertJsonPath('props.run.steps.4.status', 'waiting_for_person')
        ->assertJsonPath('props.run.artifacts.1.label', 'Public endpoint');

    $otherOwner = actingAsTestUser(0);

    $this->actingAs($otherOwner)
        ->get(route('x-change.cockpit.campaigns.lead-scenario-runner.runs.show', [
            'campaign' => $campaign->reference,
        ]))
        ->assertNotFound();
});

it('preserves the AUI settlement target from the endpoint template and offers same-code payment after intake', function (): void {
    $operator = actingAsTestUser();
    $fakeIssuer = auiLeadCampaignFakeGeneratePayCode('AUI-SETTLEMENT');
    app()->instance(GeneratePayCode::class, $fakeIssuer);

    $this->post(route('x-change.cockpit.campaigns.lead-scenario-runner.store'), [
        'scenario' => 'aui_on_demand_insurance_payment',
    ])->assertRedirect();

    $campaign = LeadCampaign::query()->sole();
    $this->post(route('x-change.leads.start.submit', [
        'merchant_slug' => $campaign->merchant_slug,
        'endpoint_slug' => $campaign->endpoint_slug,
    ]))->assertRedirect(route('x-change.claim.show', ['code' => 'AUI-SETTLEMENT']));

    $instructions = $fakeIssuer->payloads[0];
    expect($instructions['voucher_type'])->toBe('settlement')
        ->and($instructions['target_amount'])->toBe(100)
        ->and(data_get($instructions, 'cash.amount'))->toBe(0)
        ->and(data_get($instructions, 'metadata.flow_type'))->toBe('settlement')
        ->and(data_get($instructions, 'metadata.custom.settlement.driver'))->toBe('claim-intake')
        ->and(data_get($instructions, 'metadata.custom.payment.qr_delivery_modes'))
        ->toBe(['payer_page', 'downloadable'])
        ->and(data_get($instructions, 'claim.default_outcome'))->toBe('lead_intake')
        ->and(data_get($instructions, 'rider.url'))->toBeNull();

    data_set($instructions, 'metadata.collection_wallet_id', $operator->wallet->id);
    $voucher = issueVoucher(validVoucherInstructions(0, 'INSTAPAY', $instructions));

    app(SubmitWebPayCodeClaim::class)->handle($voucher, [
        'mobile' => '639171234567',
        'inputs' => [
            'name' => 'Demo Applicant',
            'mobile' => '639171234567',
            'email' => 'demo@example.test',
            'address' => 'Demo address',
            'birth_date' => '1990-01-01',
            'reference_code' => 'DEMO-001',
        ],
    ]);

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.claim.show', ['code' => $voucher->code]))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/claim/Entry');

    $this->withHeader('X-Inertia', 'false')
        ->getJson(route('x-change.claim.success', ['code' => $voucher->code]))
        ->assertOk()
        ->assertJsonPath('claimWorkflowKey', 'lead-intake.v1')
        ->assertJsonPath('success_action.label', 'Continue to payment')
        ->assertJsonPath('success_action.target.url', route('x-change.pay.show', ['code' => $voucher->code]));

    config()->set('x-change.funding.providers.netbank.enabled', true);
    config()->set('x-change.payment.attempts.enabled', true);
    config()->set('x-change.payment.attempts.provider', 'netbank');
    $adapterClass = FakeFundingProviderAdapter::class;
    app()->instance($adapterClass, new $adapterClass);
    app()->tag($adapterClass, 'emi.funding-provider-adapters');
    app()->forgetInstance(FundingProviderAdapterRegistry::class);

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.pay.show', ['code' => $voucher->code]))
        ->assertOk()
        ->assertJsonPath('props.payment.amount_due_minor', 10000)
        ->assertJsonPath('props.payment.can_create_attempt', true);

    $this->withHeader('X-Inertia', 'false')
        ->post(route('x-change.pay.attempts.store', ['code' => $voucher->code]))
        ->assertRedirect();

    $attempt = PaymentAttempt::query()->where('voucher_id', $voucher->getKey())->sole();
    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.pay.show', ['code' => $voucher->code, 'attempt' => $attempt->reference]))
        ->assertOk()
        ->assertJsonPath('props.payment.attempt.status', 'awaiting_payment')
        ->assertJsonPath('props.payment.attempt.amount_minor', 10000)
        ->assertJsonPath('props.payment.attempt.qr_code.mime_type', 'image/png')
        ->assertJsonStructure(['props' => ['payment' => ['attempt' => ['qr_code' => ['base64_payload']]]]]);

    $adapter = app($adapterClass);
    $adapter->fundingObservation = new ProviderFundingObservationData(
        provider: 'netbank',
        providerTransactionId: 'aui-demo-payment',
        grossAmountMinor: 10000,
        feeAmountMinor: 0,
        netAmountMinor: 10000,
        currency: 'PHP',
        providerStatus: 'settled',
        verificationSource: 'fake-authoritative-api',
        payloadHash: hash('sha256', 'aui-demo-payment'),
        fundingAddress: $attempt->funding_address_ciphertext,
        occurredAt: now()->toDateTimeImmutable(),
        settledAt: now()->toDateTimeImmutable(),
        metadata: ['destination_verified' => true],
    );
    $settled = app(VerifyPaymentAttempt::class)->handle($attempt, PaymentVerificationTrigger::Payer);
    $replay = app(VerifyPaymentAttempt::class)->handle($attempt, PaymentVerificationTrigger::Payer);

    expect($settled->status)->toBe(PaymentAttemptStatus::Settled)
        ->and($replay->voucher_collection_id)->toBe($settled->voucher_collection_id)
        ->and(VoucherCollection::where('voucher_id', $voucher->id)->count())->toBe(1)
        ->and(CampaignPaymentSource::query()->count())->toBe(1)
        ->and(CampaignPaymentRecognition::query()->count())->toBe(1);

    $collection = VoucherCollection::query()->whereKey($settled->voucher_collection_id)->sole();
    $recognition = CampaignPaymentRecognition::query()->sole();
    $recognitionReplay = app(RecognizeSettlementVoucherCollection::class)->handle($collection);

    expect($recognitionReplay->is($recognition))->toBeTrue()
        ->and($recognition->campaign_payment_qr_binding_id)->toBeNull()
        ->and($recognition->source?->voucher_collection_id)->toBe($collection->getKey())
        ->and($recognition->source?->payment_attempt_id)->toBe($attempt->getKey())
        ->and(VoucherCollection::query()->count())->toBe(1)
        ->and(CampaignPaymentRecognition::query()->count())->toBe(1);

    $this->get(route('x-change.pay.show', ['code' => $voucher->code, 'attempt' => $attempt->reference]))
        ->assertOk()
        ->assertJsonPath('props.payment.is_fully_paid', true)
        ->assertJsonPath('props.payment.receipt.amount_paid_minor', 10000);

    $this->actingAs($operator)
        ->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.campaigns.lead-scenario-runner.runs.show', [
            'campaign' => $campaign->reference,
        ]))
        ->assertOk()
        ->assertJsonPath('props.run.declarations.payment_evidence', 'provider_observed')
        ->assertJsonPath('props.run.steps.4.status', 'passed')
        ->assertJsonPath('props.run.steps.5.facts.pay_code', $voucher->code)
        ->assertJsonPath('props.run.steps.6.status', 'passed')
        ->assertJsonPath('props.run.steps.7.facts.payment_attempt', $attempt->reference)
        ->assertJsonPath('props.run.steps.8.status', 'passed')
        ->assertJsonPath('props.run.steps.9.status', 'passed')
        ->assertJsonPath('props.run.steps.10.status', 'passed')
        ->assertJsonPath('props.run.steps.11.status', 'running')
        ->assertJsonPath('props.run.steps.11.facts.recognition_reference', $recognition->reference)
        ->assertJsonPath('props.run.artifacts.4.reference', $voucher->code)
        ->assertJsonMissingPath('props.run.private_applicant')
        ->assertJsonMissingPath('props.run.otp');

    DB::table($collection->getTable())
        ->where('id', $collection->getKey())
        ->update(['collected_amount_minor' => $collection->collected_amount_minor + 1]);

    expect(fn () => app(RecognizeSettlementVoucherCollection::class)->handle($collection->fresh()))
        ->toThrow(InvalidArgumentException::class, 'does not match')
        ->and(CampaignPaymentRecognition::query()->count())->toBe(1);
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

    $this->post(route('x-change.leads.start.submit', [
        'merchant_slug' => $campaign->merchant_slug,
        'endpoint_slug' => $campaign->endpoint_slug,
    ]))->assertRedirect(route('x-change.claim.show', ['code' => 'AUI-LIFE-1']));

    expect($campaign->fresh()->usage_count)->toBe(1)
        ->and(data_get($fakeIssuer->payloads[0], 'cash.amount'))->toBe(25)
        ->and(data_get($fakeIssuer->payloads[0], 'feedback.mobile'))->toBe('09173011987')
        ->and(data_get($fakeIssuer->payloads[0], 'rider.message'))->toBe('test feedback')
        ->and(data_get($fakeIssuer->payloads[0], 'claim.outcomes.0.key'))->toBe('provider_disbursement')
        ->and(data_get($fakeIssuer->payloads[0], 'claim.default_outcome'))->toBe('provider_disbursement')
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
