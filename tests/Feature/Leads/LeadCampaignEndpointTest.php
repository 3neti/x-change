<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use LBHurtado\XChange\Actions\Leads\CreateLeadCampaign;
use LBHurtado\XChange\Actions\PayCode\GeneratePayCode;
use LBHurtado\XChange\Data\DebitData;
use LBHurtado\XChange\Data\IssuerData;
use LBHurtado\XChange\Data\PayCode\GeneratePayCodeResultData;
use LBHurtado\XChange\Data\PayCodeLinksData;
use LBHurtado\XChange\Data\PricingEstimateData;
use LBHurtado\XChange\Models\LeadCampaign;
use LBHurtado\XChange\Models\PayCodeTemplate;
use LBHurtado\XChange\Tests\Fakes\User;

it('creates a merchant scoped lead campaign from an owner template', function (): void {
    $operator = leadCampaignOperator('AUI Insurance');
    $template = leadCampaignTemplate($operator);

    $campaign = app(CreateLeadCampaign::class)->handle($operator, $template, [
        'title' => 'Insurance Application',
        'endpoint_slug' => 'application',
        'description' => 'Prospect intake for insurance acquisition.',
    ]);

    expect($campaign->owner_type)->toBe($operator->getMorphClass())
        ->and($campaign->owner_id)->toBe((string) $operator->getKey())
        ->and($campaign->pay_code_template_id)->toBe($template->getKey())
        ->and($campaign->merchant_display_name)->toContain('AUI Insurance')
        ->and($campaign->merchant_slug)->toStartWith('aui-insurance')
        ->and($campaign->endpoint_slug)->toBe('application')
        ->and($campaign->settings)->toMatchArray([
            'kind' => 'lead',
            'entry_point' => 'public_qr_link',
            'person_type' => 'prospect',
            'pay_code_generation' => 'on_scan',
        ]);
});

it('keeps lead campaign endpoint slugs unique under the merchant slug', function (): void {
    $operator = leadCampaignOperator('AUI Insurance');
    $firstTemplate = leadCampaignTemplate($operator, 'First template');
    $secondTemplate = leadCampaignTemplate($operator, 'Second template');

    $first = app(CreateLeadCampaign::class)->handle($operator, $firstTemplate, [
        'title' => 'Application',
        'endpoint_slug' => 'application',
    ]);
    $second = app(CreateLeadCampaign::class)->handle($operator, $secondTemplate, [
        'title' => 'Application',
        'endpoint_slug' => 'application',
    ]);

    expect($first->merchant_slug)->toBe($second->merchant_slug)
        ->and($first->endpoint_slug)->toBe('application')
        ->and($second->endpoint_slug)->toBe('application-2');
});

it('rejects lead campaigns created from another owners template', function (): void {
    $owner = leadCampaignOperator('AUI Insurance');
    $template = leadCampaignTemplate($owner);
    $other = actingAsTestUser();

    expect(fn () => app(CreateLeadCampaign::class)->handle($other, $template, [
        'title' => 'Attempted takeover',
    ]))->toThrow(AuthorizationException::class);
});

it('mints a pay code from a lead campaign endpoint and redirects into claim', function (): void {
    $operator = leadCampaignOperator('AUI Insurance');
    $template = leadCampaignTemplate($operator);
    $campaign = app(CreateLeadCampaign::class)->handle($operator, $template, [
        'title' => 'Insurance Application',
        'endpoint_slug' => 'application',
    ]);
    $fakeIssuer = leadCampaignFakeGeneratePayCode('AUI-LEAD-1');

    app()->instance(GeneratePayCode::class, $fakeIssuer);

    $this->get(route('x-change.leads.start', [
        'merchant_slug' => $campaign->merchant_slug,
        'endpoint_slug' => $campaign->endpoint_slug,
    ]))->assertRedirect(route('x-change.claim.show', ['code' => 'AUI-LEAD-1']));

    $campaign->refresh();

    expect($fakeIssuer->payloads)->toHaveCount(1)
        ->and(data_get($fakeIssuer->payloads[0], 'metadata.issuer_id'))->toBe((string) $operator->getKey())
        ->and(data_get($fakeIssuer->payloads[0], 'metadata.campaign.planning_key'))->toBe('application')
        ->and(data_get($fakeIssuer->payloads[0], 'metadata.campaign.campaign_id'))->toBe($campaign->reference)
        ->and(data_get($fakeIssuer->payloads[0], 'metadata.campaign.source'))->toBe('lead_campaign')
        ->and(data_get($fakeIssuer->payloads[0], 'metadata.custom.lead_campaign.schema'))->toBe('x-change.lead-campaign-attribution.v1')
        ->and(data_get($fakeIssuer->payloads[0], 'metadata.custom.lead_campaign.kind'))->toBe('lead')
        ->and(data_get($fakeIssuer->payloads[0], 'metadata.custom.lead_campaign.template_reference'))->toBe($template->reference)
        ->and(data_get($fakeIssuer->payloads[0], '_meta.source'))->toBe('lead_campaign.public_endpoint')
        ->and($campaign->usage_count)->toBe(1)
        ->and($campaign->last_started_at)->not->toBeNull();
});

it('does not start inactive lead campaigns', function (): void {
    $operator = leadCampaignOperator('AUI Insurance');
    $template = leadCampaignTemplate($operator);
    $campaign = app(CreateLeadCampaign::class)->handle($operator, $template, [
        'title' => 'Insurance Application',
        'endpoint_slug' => 'application',
        'status' => 'paused',
    ]);
    $fakeIssuer = leadCampaignFakeGeneratePayCode('AUI-LEAD-1');

    app()->instance(GeneratePayCode::class, $fakeIssuer);

    $this->get(route('x-change.leads.start', [
        'merchant_slug' => $campaign->merchant_slug,
        'endpoint_slug' => $campaign->endpoint_slug,
    ]))->assertNotFound();

    expect($fakeIssuer->payloads)->toBeEmpty()
        ->and($campaign->fresh()->usage_count)->toBe(0);
});

function leadCampaignOperator(string $name): User
{
    $operator = actingAsTestUser();
    $operator->forceFill(['name' => $name])->save();

    return $operator;
}

function leadCampaignTemplate(User $owner, string $name = 'Insurance application template'): PayCodeTemplate
{
    return PayCodeTemplate::query()->create([
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => (string) $owner->getKey(),
        'name' => $name,
        'base_template_key' => 'blank-pay-code',
        'instructions_ciphertext' => [
            'cash' => [
                'amount' => 0,
                'currency' => 'PHP',
            ],
            'inputs' => [
                'fields' => [
                    'name',
                    'email',
                    'mobile',
                    'address',
                    'birthday',
                    'car_registration',
                    'license_number',
                ],
            ],
            'feedback' => [],
            'rider' => [
                'message' => 'Insurance application',
            ],
            'count' => 1,
            'prefix' => 'AUI',
            'mask' => '****',
        ],
        'include_amount' => true,
        'include_purpose' => true,
        'status' => 'active',
    ]);
}

function leadCampaignFakeGeneratePayCode(string $code): GeneratePayCode
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
                voucher_id: 99001,
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
