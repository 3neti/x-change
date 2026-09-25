<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use LBHurtado\SettlementEnvelope\Contracts\WorkflowAccessPolicy;
use LBHurtado\SettlementEnvelope\Contracts\WorkflowCatalog;
use LBHurtado\SettlementEnvelope\Data\WorkflowContext;
use LBHurtado\SettlementEnvelope\Services\DenyWorkflowAccess;
use LBHurtado\SettlementEnvelope\Services\DriverService;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Models\CampaignPaymentQrBinding;
use LBHurtado\XChange\Models\CampaignWorkflowPublication;
use LBHurtado\XChange\Models\LeadCampaign;
use LBHurtado\XChange\Models\PayCodeTemplate;
use LBHurtado\XChange\Services\Cockpit\CampaignWorkflowDraftEditor;
use LBHurtado\XChange\Services\Cockpit\ConfiguredCampaignWorkflowAccess;
use LBHurtado\XChange\Services\Settlement\CampaignWorkflowPublicationSnapshot;

beforeEach(function (): void {
    $this->operator = actingAsTestUser();
    Http::preventStrayRequests();
    Storage::fake('draft-test-drivers');
    config()->set('settlement-envelope.driver_disk', 'draft-test-drivers');
    app()->forgetInstance(DriverService::class);
    $this->source = PayCodeTemplate::query()->create([
        'owner_type' => $this->operator->getMorphClass(),
        'owner_id' => (string) $this->operator->getKey(),
        'name' => 'Synthetic source', 'base_template_key' => 'blank-pay-code',
        'instructions_ciphertext' => [
            'voucher_type' => 'settlement', 'target_amount' => 50,
            'cash' => ['amount' => 0, 'currency' => 'PHP'],
            'rider' => ['message' => 'Synthetic claim details'],
        ],
        'include_amount' => true, 'include_purpose' => true, 'status' => 'active',
    ]);
    $this->payload = [
        'name' => 'AUI draft', 'pay_code_template_id' => $this->source->getKey(),
        'workflow_id' => 'aui.personal-accident.provisional-cover', 'workflow_version' => '1.0.0',
        'plan_code' => 'PA5000_DAY', 'plan_version' => '1', 'entry_method' => 'payment_qr',
    ];
});

afterEach(function (): void {
    Http::assertNothingSent();
});

function preparePublishableWorkflowDraft(object $test): PayCodeTemplate
{
    config()->set('settlement-envelope.connections.aui-demo', [
        'driver' => 'http', 'base_url' => 'https://workflow-demo.example.test',
        'auth' => ['type' => 'none'], 'connect_timeout' => 5, 'timeout' => 15,
    ]);
    $instructions = $test->source->instructions_ciphertext;
    $instructions['inputs'] = ['fields' => ['name', 'mobile', 'email', 'address', 'birth_date']];
    $test->source->update(['instructions_ciphertext' => $instructions]);
    $test->post(route('x-change.cockpit.campaigns.workflow-drafts.store'), $test->payload)->assertRedirect();

    return PayCodeTemplate::query()->where('status', 'draft')->sole();
}

it('enforces the stored template name boundary before saving', function (): void {
    $url = route('x-change.cockpit.campaigns.workflow-drafts.store');
    $this->post($url, array_replace($this->payload, ['name' => str_repeat('A', 81)]))
        ->assertSessionHasErrors('name');
    expect(PayCodeTemplate::query()->where('status', 'draft')->count())->toBe(0);
    $name = str_repeat('A', 80);
    $this->post($url, array_replace($this->payload, ['name' => $name]))
        ->assertSessionHasNoErrors()->assertRedirect();
    expect(PayCodeTemplate::query()->where('status', 'draft')->sole()->name)->toBe($name);
});

it('publishes an exact immutable demo revision once without QR or issuance side effects', function (): void {
    $draft = preparePublishableWorkflowDraft($this);
    $hash = app(CampaignWorkflowPublicationSnapshot::class)->hash($draft->instructions_ciphertext);
    $vouchers = Voucher::query()->count();
    $bindings = CampaignPaymentQrBinding::query()->count();
    $url = route('x-change.cockpit.campaigns.workflow-drafts.publish', ['draft' => $draft->reference]);
    $this->post($url, ['expected_snapshot_hash' => $hash])->assertSessionHasNoErrors()->assertRedirect();
    $publication = CampaignWorkflowPublication::query()->sole();
    $campaign = LeadCampaign::query()->findOrFail($publication->endpoint_campaign_id);
    expect($draft->refresh()->status)->toBe('published')
        ->and(data_get($campaign->settings, 'entry_mode'))->toBe('reusable_payment_qr')
        ->and($publication->snapshot['plan']['premium_minor'])->toBe(5000)
        ->and($publication->getRawOriginal('snapshot'))->not->toContain('PA5000_DAY')
        ->and($publication->published_by_id)->toBe((string) $this->operator->getKey());
    $this->post($url, ['expected_snapshot_hash' => $hash])->assertSessionHasNoErrors()->assertRedirect();
    expect(CampaignWorkflowPublication::query()->count())->toBe(1)
        ->and(LeadCampaign::query()->count())->toBe(1)
        ->and(Voucher::query()->count())->toBe($vouchers)
        ->and(CampaignPaymentQrBinding::query()->count())->toBe($bindings);
    $this->patch(route('x-change.cockpit.campaigns.endpoints.template.update', ['campaign' => $campaign->reference]), [
        'pay_code_template_id' => $this->source->getKey(),
    ])->assertSessionHasErrors('template');
    expect($campaign->refresh()->active_template_version_id)->toBe($publication->campaign_revision_id);
});

it('rejects stale draft confirmation and unavailable connection before publication', function (): void {
    $draft = preparePublishableWorkflowDraft($this);
    $url = route('x-change.cockpit.campaigns.workflow-drafts.publish', ['draft' => $draft->reference]);
    $this->post($url, ['expected_snapshot_hash' => str_repeat('a', 64)])->assertSessionHasErrors('draft');
    config()->set('settlement-envelope.connections.aui-demo', []);
    $hash = app(CampaignWorkflowPublicationSnapshot::class)->hash($draft->instructions_ciphertext);
    $this->post($url, ['expected_snapshot_hash' => $hash])->assertSessionHasErrors('draft');
    expect(LeadCampaign::query()->count())->toBe(0)
        ->and($draft->refresh()->status)->toBe('draft');
});

it('keeps reviewed BST publication unavailable instead of bypassing its evidence gates', function (): void {
    $this->payload = array_replace($this->payload, [
        'workflow_id' => 'philhealth.bst.demo', 'entry_method' => 'public_endpoint', 'plan_code' => null, 'plan_version' => null,
    ]);
    $draft = preparePublishableWorkflowDraft($this);
    $hash = app(CampaignWorkflowPublicationSnapshot::class)->hash($draft->instructions_ciphertext);
    $this->post(route('x-change.cockpit.campaigns.workflow-drafts.publish', ['draft' => $draft->reference]), ['expected_snapshot_hash' => $hash])
        ->assertSessionHasErrors('draft');
    expect(LeadCampaign::query()->count())->toBe(0);
});

it('enables the editor by default without account grants or changing the global workflow catalog', function (): void {
    app()->bind(WorkflowAccessPolicy::class, DenyWorkflowAccess::class);
    $identity = $this->operator->getMorphClass().':'.$this->operator->getKey();
    expect(config('x-change-workflows.enabled'))->toBeTrue()
        ->and(app(CampaignWorkflowDraftEditor::class)->for($this->operator)['workflows'])->toHaveCount(2)
        ->and(app(WorkflowCatalog::class)->available(new WorkflowContext($identity, $identity)))->toBe([]);
    $policy = new ConfiguredCampaignWorkflowAccess;
    expect($policy->allows(new WorkflowContext($identity, $identity), $this->payload['workflow_id'], '1.0.0'))->toBeTrue()
        ->and($policy->allows(new WorkflowContext('other:1', $identity), $this->payload['workflow_id'], '1.0.0'))->toBeFalse();
    expect(fn () => new WorkflowContext('', ''))->toThrow(InvalidArgumentException::class);
});

it('saves a private inactive template snapshot without publishing a campaign', function (): void {
    $original = $this->source->instructions_ciphertext;
    $campaigns = LeadCampaign::query()->count();
    $vouchers = Voucher::query()->count();
    $bindings = CampaignPaymentQrBinding::query()->count();
    $this->post(route('x-change.cockpit.campaigns.workflow-drafts.store'), $this->payload)->assertRedirect();
    $draft = PayCodeTemplate::query()->where('status', 'draft')->sole();
    $snapshot = data_get($draft->instructions_ciphertext, CampaignWorkflowDraftEditor::SNAPSHOT_PATH);
    expect($snapshot['workflow']['id'])->toBe($this->payload['workflow_id'])
        ->and($snapshot['workflow']['version'])->toBe('1.0.0')
        ->and($snapshot['plan']['premium_minor'])->toBe(5000)
        ->and($snapshot['plan']['benefit_minor'])->toBe(500000)
        ->and($snapshot['state'])->toBe('draft_only')
        ->and($snapshot['source_template_reference'])->toBe($this->source->reference)
        ->and($draft->getRawOriginal('instructions_ciphertext'))->not->toContain('PA5000_DAY')
        ->and($this->source->refresh()->instructions_ciphertext)->toBe($original)
        ->and(LeadCampaign::query()->count())->toBe($campaigns)
        ->and(Voucher::query()->count())->toBe($vouchers)
        ->and(CampaignPaymentQrBinding::query()->count())->toBe($bindings);
    $this->source->update(['instructions_ciphertext' => ['cash' => ['amount' => 999]]]);
    expect(data_get($draft->refresh()->instructions_ciphertext, 'cash.amount'))->toBe(0)
        ->and(data_get($draft->instructions_ciphertext, CampaignWorkflowDraftEditor::SNAPSHOT_PATH.'.plan.premium_minor'))->toBe(5000);
    $this->withHeader('X-Inertia', 'true')->get(route('x-change.cockpit.campaigns.index'))
        ->assertOk()->assertJsonCount(1, 'props.pay_code_templates')
        ->assertJsonPath('props.pay_code_templates.0.id', $this->source->getKey())
        ->assertJsonCount(1, 'props.workflow_drafts.drafts')
        ->assertJsonPath('props.workflow_drafts.drafts.0.reference', $draft->reference);
    $this->post(route('x-change.cockpit.campaigns.endpoints.store'), [
        'title' => 'Must not publish', 'pay_code_template_id' => $draft->getKey(), 'usage_key' => 'lead',
    ])->assertSessionHasErrors('template');
    expect(LeadCampaign::query()->count())->toBe($campaigns);
});

it('saves a planless reviewed BST draft with document requirements but no approval', function (): void {
    $this->post(route('x-change.cockpit.campaigns.workflow-drafts.store'), array_replace($this->payload, [
        'workflow_id' => 'philhealth.bst.demo', 'plan_code' => null, 'plan_version' => null,
        'entry_method' => 'public_endpoint',
    ]))->assertRedirect();
    $snapshot = data_get(PayCodeTemplate::query()->where('status', 'draft')->sole()->instructions_ciphertext, CampaignWorkflowDraftEditor::SNAPSHOT_PATH);
    expect($snapshot['plan'])->toBeNull()
        ->and($snapshot['workflow']['workflow']['requires_review'])->toBeTrue()
        ->and($snapshot['workflow']['documents'])->toHaveCount(2)
        ->and($snapshot['workflow']['gates'])->toContain('settleable');
});

it('rejects invalid workflow selections and private overrides without saving', function (array $changes, string $field): void {
    $this->post(route('x-change.cockpit.campaigns.workflow-drafts.store'), array_replace($this->payload, $changes))
        ->assertSessionHasErrors($field);
    expect(PayCodeTemplate::query()->count())->toBe(1);
})->with([
    [['workflow_version' => '99.0.0'], 'workflow_id'],
    [['entry_method' => 'public_endpoint'], 'entry_method'],
    [['plan_version' => '99'], 'plan_code'],
    [['plan_code' => null, 'plan_version' => null], 'plan_code'],
    [['connection' => 'https://attacker.test'], 'connection'],
    [['instructions' => ['cash' => ['amount' => 999]]], 'instructions'],
    [['parameters' => ['premium_minor' => 1]], 'parameters'],
    [['notifications' => ['sms' => 'Forged']], 'notifications'],
    [['status' => 'active'], 'status'],
    [['workflow_id' => 'philhealth.bst.demo', 'entry_method' => 'public_endpoint'], 'plan_code'],
]);

it('refuses a payment QR backed by a disbursable template', function (): void {
    $this->source->update(['instructions_ciphertext' => ['voucher_type' => 'disbursable', 'cash' => ['amount' => 50]]]);
    $this->post(route('x-change.cockpit.campaigns.workflow-drafts.store'), $this->payload)->assertSessionHasErrors('pay_code_template_id');
});

it('shares workflow discovery but isolates templates and drafts between accounts', function (): void {
    $this->post(route('x-change.cockpit.campaigns.workflow-drafts.store'), $this->payload)->assertRedirect();
    expect(PayCodeTemplate::query()->where('status', 'draft')->count())->toBe(1);
    $other = actingAsTestUser();
    $this->post(route('x-change.cockpit.campaigns.workflow-drafts.store'), $this->payload)->assertNotFound();
    $this->withHeader('X-Inertia', 'true')->get(route('x-change.cockpit.campaigns.index'))
        ->assertOk()->assertJsonCount(2, 'props.workflow_drafts.workflows')
        ->assertJsonPath('props.workflow_drafts.drafts', []);
    $source = $this->source->replicate();
    $source->reference = null;
    $source->owner_id = (string) $other->getKey();
    $source->save();
    $this->post(route('x-change.cockpit.campaigns.workflow-drafts.store'), array_replace($this->payload, [
        'pay_code_template_id' => $source->getKey(),
    ]))->assertSessionHasNoErrors()->assertRedirect();
    expect(PayCodeTemplate::query()->where('status', 'draft')->count())->toBe(2);
});

it('does not expose connection references or credentials in the editor props', function (): void {
    config()->set('settlement-envelope.connections.aui-demo.auth.token', 'test-secret');
    $props = app(CampaignWorkflowDraftEditor::class)->for($this->operator);
    expect($props['workflows'])->toHaveCount(2)
        ->and(json_encode($props))->not->toContain('test-secret')
        ->and($props['workflows'][0])->not->toHaveKey('connection');
});

it('fails closed when the host disables workflows between preview and save', function (): void {
    expect(app(CampaignWorkflowDraftEditor::class)->for($this->operator)['workflows'])->toHaveCount(2);
    config()->set('x-change-workflows.enabled', false);
    $this->post(route('x-change.cockpit.campaigns.workflow-drafts.store'), $this->payload)->assertSessionHasErrors('workflow_id');
    expect(app(CampaignWorkflowDraftEditor::class)->for($this->operator)['workflows'])->toBe([])
        ->and(PayCodeTemplate::query()->count())->toBe(1);
});

it('rejects publication when workflows are disabled or the draft belongs to another account', function (): void {
    $draft = preparePublishableWorkflowDraft($this);
    $url = route('x-change.cockpit.campaigns.workflow-drafts.publish', ['draft' => $draft->reference]);
    $payload = ['expected_snapshot_hash' => app(CampaignWorkflowPublicationSnapshot::class)->hash($draft->instructions_ciphertext)];
    config()->set('x-change-workflows.enabled', false);
    $this->post($url, $payload)->assertSessionHasErrors('draft');
    config()->set('x-change-workflows.enabled', true);
    actingAsTestUser();
    $this->post($url, $payload)->assertNotFound();
    expect(CampaignWorkflowPublication::query()->count())->toBe(0)
        ->and($draft->refresh()->status)->toBe('draft');
});

it('rejects incomplete or incompatible source instructions without creating a draft', function (array $instructions): void {
    $this->source->update(['instructions_ciphertext' => $instructions]);
    $this->post(route('x-change.cockpit.campaigns.workflow-drafts.store'), $this->payload)->assertSessionHasErrors('pay_code_template_id');
    expect(PayCodeTemplate::query()->count())->toBe(1);
})->with([
    [[]],
    [['voucher_type' => 'settlement', 'cash' => ['amount' => 0, 'currency' => 'USD']]],
]);
