<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use LBHurtado\XCampaign\Models\EndpointCampaign;
use LBHurtado\XChange\Actions\Leads\CreateLeadCampaign;
use LBHurtado\XChange\Actions\Leads\StartLeadCampaign;
use LBHurtado\XChange\Actions\PayCode\GeneratePayCode;
use LBHurtado\XChange\Data\DebitData;
use LBHurtado\XChange\Data\IssuerData;
use LBHurtado\XChange\Data\PayCode\GeneratePayCodeResultData;
use LBHurtado\XChange\Data\PayCodeLinksData;
use LBHurtado\XChange\Data\PricingEstimateData;
use LBHurtado\XChange\Models\LeadCampaign;
use LBHurtado\XChange\Models\PayCodeTemplate;
use LBHurtado\XChange\Services\Leads\LeadCampaignTemplateVersionId;
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
        ->and($campaign->active_template_version_id)->toBe(app(LeadCampaignTemplateVersionId::class)->forTemplate($template))
        ->and($campaign->merchant_display_name)->toContain('AUI Insurance')
        ->and($campaign->merchant_slug)->toStartWith('aui-insurance')
        ->and($campaign->endpoint_slug)->toBe('application')
        ->and($campaign->settings)->toMatchArray([
            'kind' => 'lead',
            'entry_point' => 'public_qr_link',
            'entry_mode' => 'pay_code_on_open',
            'person_type' => 'prospect',
            'pay_code_generation' => 'on_scan',
        ]);
});

it('retains the persisted endpoint identity and template relation after extraction', function (): void {
    $operator = leadCampaignOperator('Compatibility merchant');
    $template = leadCampaignTemplate($operator);
    $campaign = app(CreateLeadCampaign::class)->handle($operator, $template, [
        'title' => 'Existing public link',
        'endpoint_slug' => 'existing-public-link',
    ]);
    $reference = $campaign->reference;
    $url = route('x-change.leads.start', [
        'merchant_slug' => $campaign->merchant_slug,
        'endpoint_slug' => $campaign->endpoint_slug,
    ]);
    $campaign->refresh();

    expect($campaign)->toBeInstanceOf(EndpointCampaign::class)
        ->and($campaign->getTable())->toBe('x_change_lead_campaigns')
        ->and($campaign->getMorphClass())->toBe(LeadCampaign::class)
        ->and($campaign->reference)->toBe($reference)
        ->and($campaign->reference)->not->toBeEmpty()
        ->and($campaign->owner->is($operator))->toBeTrue()
        ->and($campaign->payCodeTemplate->is($template))->toBeTrue()
        ->and($campaign->usage_count)->toBe(0)
        ->and($campaign->status)->toBe('active')
        ->and(route('x-change.leads.start', [
            'merchant_slug' => $campaign->merchant_slug,
            'endpoint_slug' => $campaign->endpoint_slug,
        ]))->toBe($url);
});

it('rejects unavailable endpoint starts before issuing or incrementing usage', function (array $attributes): void {
    $this->travelTo(Carbon::parse('2026-09-18 04:00:00', 'UTC'));
    $operator = leadCampaignOperator('Availability merchant');
    $template = leadCampaignTemplate($operator);
    $campaign = app(CreateLeadCampaign::class)->handle($operator, $template, [
        'title' => 'Availability baseline', ...$attributes,
    ]);
    $fakeIssuer = leadCampaignFakeGeneratePayCode('SHOULD-NOT-ISSUE');
    app()->instance(GeneratePayCode::class, $fakeIssuer);

    $url = route('x-change.leads.start', [
        'merchant_slug' => $campaign->merchant_slug,
        'endpoint_slug' => $campaign->endpoint_slug,
    ]);

    $this->withHeader('X-Inertia', 'true')->get($url)->assertOk();
    $this->post(route('x-change.leads.start.submit', [
        'merchant_slug' => $campaign->merchant_slug,
        'endpoint_slug' => $campaign->endpoint_slug,
    ]))->assertRedirect($url)
        ->assertSessionHasErrors('campaign');

    expect($fakeIssuer->payloads)->toBeEmpty()
        ->and($campaign->fresh()->usage_count)->toBe(0)
        ->and($campaign->fresh()->last_started_at)->toBeNull();
})->with([
    'expired' => [['expires_at' => '2026-09-17 00:00:00']],
    'zero start limit' => [['starts_limit' => 0]],
    'outside local hours' => [['settings' => ['availability' => [
        'timezone' => 'Asia/Manila', 'daily_window_start' => '13:00', 'daily_window_end' => '14:00',
    ]]]],
]);

it('does not record a successful start when issuance fails', function (): void {
    $operator = leadCampaignOperator('Failed issuance merchant');
    $campaign = app(CreateLeadCampaign::class)->handle($operator, leadCampaignTemplate($operator), [
        'title' => 'Failed issuance',
    ]);
    $issuer = Mockery::mock(GeneratePayCode::class);
    $issuer->shouldReceive('handle')->once()->andThrow(new RuntimeException('Issuance failed'));
    app()->instance(GeneratePayCode::class, $issuer);

    expect(fn () => app(StartLeadCampaign::class)->handle($campaign))->toThrow(RuntimeException::class, 'Issuance failed');
    expect($campaign->fresh()->usage_count)->toBe(0)
        ->and($campaign->fresh()->last_started_at)->toBeNull();
});

it('rejects a missing public endpoint without issuing a Pay Code', function (): void {
    $issuer = leadCampaignFakeGeneratePayCode('SHOULD-NOT-ISSUE');
    app()->instance(GeneratePayCode::class, $issuer);

    $this->get(route('x-change.leads.start', [
        'merchant_slug' => 'missing', 'endpoint_slug' => 'missing',
    ]))->assertNotFound();

    expect($issuer->payloads)->toBeEmpty();
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

it('lists endpoint summaries with batched template loading and no endpoint writes', function (): void {
    $owner = leadCampaignOperator('List merchant');
    foreach (range(1, 3) as $number) {
        $template = leadCampaignTemplate($owner, 'Template '.$number);
        app(CreateLeadCampaign::class)->handle($owner, $template, [
            'title' => 'Endpoint '.$number,
        ]);
    }
    $before = LeadCampaign::query()->orderBy('id')->get()->map->getAttributes()->all();
    DB::enableQueryLog();
    DB::flushQueryLog();

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.campaigns.index'))
        ->assertOk()
        ->assertJsonCount(3, 'props.endpoint_campaigns')
        ->assertJsonPath('props.endpoint_campaigns.0.usage_key', 'lead')
        ->assertJsonPath('props.endpoint_campaigns.0.usage_count', 0)
        ->assertJsonPath('props.endpoint_campaigns.0.template.currency', 'PHP');

    $queries = collect(DB::getQueryLog())->pluck('query');
    DB::disableQueryLog();
    $endpointQueries = $queries->filter(fn (string $sql): bool => str_contains($sql, '"x_change_lead_campaigns"'));
    $templateQueries = $queries->filter(fn (string $sql): bool => str_contains($sql, '"x_change_pay_code_templates"'));

    expect($endpointQueries)->toHaveCount(1)
        ->and(strtolower($endpointQueries->sole()))->toStartWith('select')
        ->and($templateQueries)->toHaveCount(2)
        ->and(LeadCampaign::query()->orderBy('id')->get()->map->getAttributes()->all())->toBe($before);
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

    $url = route('x-change.leads.start', [
        'merchant_slug' => $campaign->merchant_slug,
        'endpoint_slug' => $campaign->endpoint_slug,
    ]);

    $this->withHeader('X-Inertia', 'true')->get($url)->assertOk();

    expect($fakeIssuer->payloads)->toBeEmpty()
        ->and($campaign->fresh()->usage_count)->toBe(0);

    $this->post(route('x-change.leads.start.submit', [
        'merchant_slug' => $campaign->merchant_slug,
        'endpoint_slug' => $campaign->endpoint_slug,
    ]))->assertRedirect(route('x-change.claim.show', ['code' => 'AUI-LEAD-1']));

    $campaign->refresh();

    expect($fakeIssuer->payloads)->toHaveCount(1)
        ->and(data_get($fakeIssuer->payloads[0], 'metadata.issuer_id'))->toBe((string) $operator->getKey())
        ->and(data_get($fakeIssuer->payloads[0], 'metadata.campaign.planning_key'))->toBe('application')
        ->and(data_get($fakeIssuer->payloads[0], 'metadata.campaign.campaign_id'))->toBe($campaign->reference)
        ->and(data_get($fakeIssuer->payloads[0], 'metadata.campaign.source'))->toBe('lead_campaign')
        ->and(data_get($fakeIssuer->payloads[0], 'metadata.campaign.template_reference'))->toBe($template->reference)
        ->and(data_get($fakeIssuer->payloads[0], 'metadata.campaign.template_version_id'))->toBe($campaign->active_template_version_id)
        ->and(data_get($fakeIssuer->payloads[0], 'metadata.custom.lead_campaign.schema'))->toBe('x-change.lead-campaign-attribution.v1')
        ->and(data_get($fakeIssuer->payloads[0], 'metadata.custom.lead_campaign.kind'))->toBe('lead')
        ->and(data_get($fakeIssuer->payloads[0], 'metadata.custom.lead_campaign.template_reference'))->toBe($template->reference)
        ->and(data_get($fakeIssuer->payloads[0], 'metadata.custom.lead_campaign.template_version_id'))->toBe($campaign->active_template_version_id)
        ->and(data_get($fakeIssuer->payloads[0], '_meta.source'))->toBe('lead_campaign.public_endpoint')
        ->and($campaign->usage_count)->toBe(1)
        ->and($campaign->last_started_at)->not->toBeNull();
});

it('returns the same pay code for duplicate starts in the same browser window', function (): void {
    $operator = leadCampaignOperator('Idempotent merchant');
    $campaign = app(CreateLeadCampaign::class)->handle($operator, leadCampaignTemplate($operator), [
        'title' => 'Idempotent insurance application',
        'endpoint_slug' => 'idempotent-application',
    ]);
    $fakeIssuer = leadCampaignFakeGeneratePayCode('AUI-ONCE');
    app()->instance(GeneratePayCode::class, $fakeIssuer);

    $startRoute = route('x-change.leads.start.submit', [
        'merchant_slug' => $campaign->merchant_slug,
        'endpoint_slug' => $campaign->endpoint_slug,
    ]);

    $this->post($startRoute)
        ->assertRedirect(route('x-change.claim.show', ['code' => 'AUI-ONCE']));
    $this->post($startRoute)
        ->assertRedirect(route('x-change.claim.show', ['code' => 'AUI-ONCE']));

    expect($fakeIssuer->payloads)->toHaveCount(1)
        ->and($campaign->fresh()->usage_count)->toBe(1);
});

it('rate limits excessive endpoint campaign starts before another code can be minted', function (): void {
    config()->set('x-change.leads.rate_limits.start_per_minute', 1);
    config()->set('x-change.leads.rate_limits.start_per_hour', 50);
    config()->set('x-change.leads.rate_limits.endpoint_start_per_day', 50);
    RateLimiter::clear('start:minute:127.0.0.1');
    RateLimiter::clear('start:hour:127.0.0.1');

    $operator = leadCampaignOperator('Rate limited merchant');
    $campaign = app(CreateLeadCampaign::class)->handle($operator, leadCampaignTemplate($operator), [
        'title' => 'Rate limited application',
        'endpoint_slug' => 'limited-application',
    ]);
    $fakeIssuer = leadCampaignFakeGeneratePayCode('AUI-LIMITED');
    app()->instance(GeneratePayCode::class, $fakeIssuer);

    $startRoute = route('x-change.leads.start.submit', [
        'merchant_slug' => $campaign->merchant_slug,
        'endpoint_slug' => $campaign->endpoint_slug,
    ]);

    $this->post($startRoute)->assertRedirect(route('x-change.claim.show', ['code' => 'AUI-LIMITED']));
    $this->post($startRoute)->assertTooManyRequests();

    expect($fakeIssuer->payloads)->toHaveCount(1);
});

it('keeps the public endpoint URL stable while future starts use the updated template version', function (): void {
    $operator = leadCampaignOperator('AUI Insurance');
    $firstTemplate = leadCampaignTemplate($operator, 'Original application');
    $secondTemplate = leadCampaignTemplate($operator, 'Updated application');
    $secondInstructions = $secondTemplate->instructions_ciphertext;
    data_set($secondInstructions, 'cash.amount', 2500);
    data_set($secondInstructions, 'rider.message', 'Updated insurance application');
    $secondTemplate->update(['instructions_ciphertext' => $secondInstructions]);
    $campaign = app(CreateLeadCampaign::class)->handle($operator, $firstTemplate, [
        'title' => 'Insurance Application',
        'endpoint_slug' => 'application',
    ]);
    $publicRoute = route('x-change.leads.start', [
        'merchant_slug' => $campaign->merchant_slug,
        'endpoint_slug' => $campaign->endpoint_slug,
    ]);
    $firstVersion = app(LeadCampaignTemplateVersionId::class)->forTemplate($firstTemplate);
    $updatedVersion = app(LeadCampaignTemplateVersionId::class)->forTemplate($secondTemplate);
    $fakeIssuer = leadCampaignFakeGeneratePayCode('AUI-LEAD');
    app()->instance(GeneratePayCode::class, $fakeIssuer);

    $this->post(route('x-change.leads.start.submit', [
        'merchant_slug' => $campaign->merchant_slug,
        'endpoint_slug' => $campaign->endpoint_slug,
    ]))->assertRedirect(route('x-change.claim.show', ['code' => 'AUI-LEAD']));

    $audit = fakeAuditLogger();
    $this->patch(route('x-change.cockpit.campaigns.endpoints.template.update', $campaign->reference), [
        'pay_code_template_id' => $secondTemplate->getKey(),
    ])->assertRedirect(route('x-change.cockpit.campaigns.index'))
        ->assertSessionHas('campaign_notice', 'Insurance Application will use Updated application for future starts. Existing Pay Codes remain untouched.');

    session()->flush();

    $this->post(route('x-change.leads.start.submit', [
        'merchant_slug' => $campaign->fresh()->merchant_slug,
        'endpoint_slug' => $campaign->fresh()->endpoint_slug,
    ]))->assertRedirect(route('x-change.claim.show', ['code' => 'AUI-LEAD']));

    $templateChangeAudit = collect($audit->events('campaign.endpoint.template_version_changed'))->first();

    expect(route('x-change.leads.start', [
        'merchant_slug' => $campaign->fresh()->merchant_slug,
        'endpoint_slug' => $campaign->fresh()->endpoint_slug,
    ]))->toBe($publicRoute)
        ->and($fakeIssuer->payloads)->toHaveCount(2)
        ->and(data_get($fakeIssuer->payloads[0], 'metadata.custom.lead_campaign.template_reference'))->toBe($firstTemplate->reference)
        ->and(data_get($fakeIssuer->payloads[0], 'metadata.custom.lead_campaign.template_version_id'))->toBe($firstVersion)
        ->and(data_get($fakeIssuer->payloads[1], 'cash.amount'))->toBe(2500)
        ->and(data_get($fakeIssuer->payloads[1], 'rider.message'))->toBe('Updated insurance application')
        ->and(data_get($fakeIssuer->payloads[1], 'metadata.custom.lead_campaign.template_reference'))->toBe($secondTemplate->reference)
        ->and(data_get($fakeIssuer->payloads[1], 'metadata.custom.lead_campaign.template_version_id'))->toBe($updatedVersion)
        ->and($campaign->fresh()->pay_code_template_id)->toBe($secondTemplate->getKey())
        ->and($campaign->fresh()->active_template_version_id)->toBe($updatedVersion)
        ->and($campaign->fresh()->usage_count)->toBe(2)
        ->and($templateChangeAudit['context']['previous_template_reference'] ?? null)->toBe($firstTemplate->reference)
        ->and($templateChangeAudit['context']['previous_template_version_id'] ?? null)->toBe($firstVersion)
        ->and($templateChangeAudit['context']['template_reference'] ?? null)->toBe($secondTemplate->reference)
        ->and($templateChangeAudit['context']['template_version_id'] ?? null)->toBe($updatedVersion);
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

    $url = route('x-change.leads.start', [
        'merchant_slug' => $campaign->merchant_slug,
        'endpoint_slug' => $campaign->endpoint_slug,
    ]);

    $this->withHeader('X-Inertia', 'true')->get($url)->assertOk();
    $this->post(route('x-change.leads.start.submit', [
        'merchant_slug' => $campaign->merchant_slug,
        'endpoint_slug' => $campaign->endpoint_slug,
    ]))->assertRedirect($url)
        ->assertSessionHasErrors('campaign');

    expect($fakeIssuer->payloads)->toBeEmpty()
        ->and($campaign->fresh()->usage_count)->toBe(0);
});

it('does not start endpoint campaigns before their availability window', function (): void {
    $operator = leadCampaignOperator('AUI Insurance');
    $template = leadCampaignTemplate($operator);
    $campaign = app(CreateLeadCampaign::class)->handle($operator, $template, [
        'title' => 'Insurance Application',
        'endpoint_slug' => 'application',
        'settings' => [
            'usage_key' => 'lead',
            'availability' => [
                'starts_at' => now()->addDay()->toIso8601String(),
                'timezone' => config('app.timezone', 'UTC'),
            ],
        ],
    ]);
    $fakeIssuer = leadCampaignFakeGeneratePayCode('AUI-LEAD-1');

    app()->instance(GeneratePayCode::class, $fakeIssuer);

    $url = route('x-change.leads.start', [
        'merchant_slug' => $campaign->merchant_slug,
        'endpoint_slug' => $campaign->endpoint_slug,
    ]);

    $this->withHeader('X-Inertia', 'true')->get($url)->assertOk();
    $this->post(route('x-change.leads.start.submit', [
        'merchant_slug' => $campaign->merchant_slug,
        'endpoint_slug' => $campaign->endpoint_slug,
    ]))->assertRedirect($url)
        ->assertSessionHasErrors('campaign');

    expect($fakeIssuer->payloads)->toBeEmpty()
        ->and($campaign->fresh()->usage_count)->toBe(0);
});

it('records configured endpoint campaign usage metadata on generated pay codes', function (): void {
    $operator = leadCampaignOperator('AUI Insurance');
    $template = leadCampaignTemplate($operator);
    $campaign = app(CreateLeadCampaign::class)->handle($operator, $template, [
        'title' => 'Insurance Collection',
        'endpoint_slug' => 'insurance-payment',
        'settings' => [
            'usage_key' => 'collection',
            'usage_label' => 'Collection',
            'capabilities' => ['public_endpoint', 'collection'],
            'entry_point' => 'public_qr_link',
            'person_type' => 'payer',
            'pay_code_generation' => 'on_invoice_or_scan',
        ],
    ]);
    $fakeIssuer = leadCampaignFakeGeneratePayCode('AUI-COLL-1');

    app()->instance(GeneratePayCode::class, $fakeIssuer);

    $this->post(route('x-change.leads.start.submit', [
        'merchant_slug' => $campaign->merchant_slug,
        'endpoint_slug' => $campaign->endpoint_slug,
    ]))->assertRedirect(route('x-change.claim.show', ['code' => 'AUI-COLL-1']));

    expect(data_get($fakeIssuer->payloads[0], 'metadata.custom.lead_campaign.kind'))->toBe('collection')
        ->and(data_get($fakeIssuer->payloads[0], 'metadata.custom.lead_campaign.usage_label'))->toBe('Collection')
        ->and(data_get($fakeIssuer->payloads[0], 'metadata.custom.lead_campaign.capabilities'))->toBe(['public_endpoint', 'collection'])
        ->and(data_get($fakeIssuer->payloads[0], 'metadata.custom.lead_campaign.person_type'))->toBe('payer')
        ->and(data_get($fakeIssuer->payloads[0], 'metadata.custom.lead_campaign.pay_code_generation'))->toBe('on_invoice_or_scan');
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
