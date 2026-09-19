<?php

declare(strict_types=1);

use LBHurtado\XChange\Actions\Keepsake\PlanInstanceKeepsakeExport;
use LBHurtado\XChange\Exceptions\InstanceKeepsakeException;
use LBHurtado\XChange\Models\LeadCampaign;
use LBHurtado\XChange\Models\PayCodeTemplate;
use LBHurtado\XChange\Models\ProviderBalanceSnapshot;
use LBHurtado\XChange\Tests\Fakes\User;

it('captures owner-scoped campaigns and persisted provider checkpoints without private settings', function () {
    config()->set('x-change.instance.id', 'source-one');
    $owner = User::query()->create([
        'name' => 'Campaign Owner',
        'email' => 'owner@example.test',
        'password' => 'secret',
    ]);
    $other = User::query()->create([
        'name' => 'Other Owner',
        'email' => 'other@example.test',
        'password' => 'secret',
    ]);
    $template = PayCodeTemplate::query()->create([
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => (string) $owner->getKey(),
        'name' => 'Owner template',
        'base_template_key' => 'blank-pay-code',
        'instructions_ciphertext' => ['cash' => ['amount' => 100, 'currency' => 'PHP']],
        'include_amount' => true,
        'include_purpose' => true,
        'status' => 'active',
    ]);
    $otherTemplate = PayCodeTemplate::query()->create([
        'owner_type' => $other->getMorphClass(),
        'owner_id' => (string) $other->getKey(),
        'name' => 'Other template',
        'base_template_key' => 'blank-pay-code',
        'instructions_ciphertext' => ['cash' => ['amount' => 200, 'currency' => 'PHP']],
        'include_amount' => true,
        'include_purpose' => true,
        'status' => 'active',
    ]);
    LeadCampaign::query()->create([
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => (string) $owner->getKey(),
        'pay_code_template_id' => $template->getKey(),
        'merchant_display_name' => 'Owner Merchant',
        'merchant_slug' => 'owner-merchant',
        'endpoint_slug' => 'owner-offer',
        'title' => 'Owner Offer',
        'status' => 'active',
        'settings' => ['provider_secret' => 'never-export'],
    ]);
    LeadCampaign::query()->create([
        'owner_type' => $other->getMorphClass(),
        'owner_id' => (string) $other->getKey(),
        'pay_code_template_id' => $otherTemplate->getKey(),
        'merchant_display_name' => 'Other Merchant',
        'merchant_slug' => 'other-merchant',
        'endpoint_slug' => 'other-offer',
        'title' => 'Other Offer',
        'status' => 'paused',
    ]);
    ProviderBalanceSnapshot::query()->create([
        'provider_code' => 'netbank',
        'balance_key' => 'netbank_source_account',
        'scope_key' => 'global',
        'balance_minor' => 100_000,
        'available_balance_minor' => 90_000,
        'currency' => 'PHP',
        'account_reference_masked' => '********0001',
        'fetched_at' => now(),
        'refresh_status' => 'fresh',
        'failure_reason' => 'must not enter the keepsake',
    ]);

    $plan = app(PlanInstanceKeepsakeExport::class)->handle(
        allUsers: false,
        userIdentifiers: [$owner->email],
        includes: ['campaigns', 'continuity', 'blueprint'],
        includePersonalData: true,
        includeLocationData: false,
        allowIncomplete: false,
        materializeArtifacts: false,
    );
    $files = collect($plan->contributions)
        ->flatMap(fn ($contribution): array => [...$contribution->snapshotFiles, ...$contribution->blueprintFiles]);
    $campaigns = json_decode($files['snapshot/endpoint-campaigns.json'], true, flags: JSON_THROW_ON_ERROR);
    $blueprints = json_decode($files['blueprint/endpoint-campaigns.json'], true, flags: JSON_THROW_ON_ERROR);
    $checkpoint = json_decode($files['snapshot/continuity-checkpoint.json'], true, flags: JSON_THROW_ON_ERROR);

    expect($campaigns['campaigns'])->toHaveCount(1)
        ->and($campaigns['campaigns'][0]['title'])->toBe('Owner Offer')
        ->and($campaigns['campaigns'][0]['settings_included'])->toBeFalse()
        ->and($blueprints['campaigns'][0]['desired_state'])->toBe('disabled')
        ->and($files['snapshot/endpoint-campaigns.json'])->not->toContain('provider_secret')
        ->and($checkpoint['source_instance']['id'])->toBe('source-one')
        ->and($checkpoint['provider_balance_snapshots'])->toHaveCount(1)
        ->and($checkpoint['provider_balance_snapshots'][0]['is_stale'])->toBeFalse()
        ->and($checkpoint['provider_balance_snapshots'][0]['maximum_age_seconds'])->toBe(300)
        ->and($files['snapshot/continuity-checkpoint.json'])->not->toContain('must not enter');

    fakePayoutProvider()->assertNoDisbursementAttempted();
    expect(fakePayoutProvider()->checkStatusCallCount)->toBe(0);
});

it('enforces the campaign checkpoint safety limit', function () {
    config()->set('x-change.instance_keepsake.max_campaigns', 0);
    $owner = User::query()->create([
        'name' => 'Campaign Owner',
        'email' => 'owner@example.test',
        'password' => 'secret',
    ]);
    $template = PayCodeTemplate::query()->create([
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => (string) $owner->getKey(),
        'name' => 'Owner template',
        'base_template_key' => 'blank-pay-code',
        'instructions_ciphertext' => ['cash' => ['amount' => 100, 'currency' => 'PHP']],
        'include_amount' => true,
        'include_purpose' => true,
        'status' => 'active',
    ]);
    LeadCampaign::query()->create([
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => (string) $owner->getKey(),
        'pay_code_template_id' => $template->getKey(),
        'merchant_display_name' => 'Owner Merchant',
        'merchant_slug' => 'owner-merchant',
        'endpoint_slug' => 'owner-offer',
        'title' => 'Owner Offer',
        'status' => 'active',
    ]);

    expect(fn () => app(PlanInstanceKeepsakeExport::class)->handle(
        allUsers: true,
        userIdentifiers: [],
        includes: ['campaigns'],
        includePersonalData: false,
        includeLocationData: false,
        allowIncomplete: false,
        materializeArtifacts: false,
    ))->toThrow(InstanceKeepsakeException::class, 'campaign limit');
});
