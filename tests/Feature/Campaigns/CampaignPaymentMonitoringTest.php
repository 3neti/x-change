<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use LBHurtado\EmiCore\Enums\FundingAddressPurpose;
use LBHurtado\XChange\Actions\Campaigns\SetCampaignPaymentMonitoring;
use LBHurtado\XChange\Enums\CampaignEntryMode;
use LBHurtado\XChange\Enums\CampaignPaymentAmountMode;
use LBHurtado\XChange\Enums\CampaignPaymentMonitoringMode;
use LBHurtado\XChange\Enums\FundingAddressStatus;
use LBHurtado\XChange\Enums\FundingRecognitionMode;
use LBHurtado\XChange\Enums\StandingFundingRuntimeMode;
use LBHurtado\XChange\Jobs\Funding\SyncStandingFundingAddressJob;
use LBHurtado\XChange\Models\CampaignPaymentMonitoringControl;
use LBHurtado\XChange\Models\CampaignPaymentQrBinding;
use LBHurtado\XChange\Models\LeadCampaign;
use LBHurtado\XChange\Models\PayCodeTemplate;
use LBHurtado\XChange\Models\StandingFundingAddress;
use LBHurtado\XChange\Models\StandingFundingRuntimeControl;

it('fails closed when monitoring control is absent and generation-fences explicit transitions', function (): void {
    [$owner, $campaign, $binding] = monitoredCampaignFixture();

    expect($binding->monitoringControl)->toBeNull();

    $control = app(SetCampaignPaymentMonitoring::class)->handle(
        binding: $binding,
        mode: CampaignPaymentMonitoringMode::Live,
        expectedGeneration: 0,
        reason: 'operator_started',
        actorType: $owner->getMorphClass(),
        actorId: (string) $owner->getKey(),
    );

    expect($control->mode)->toBe(CampaignPaymentMonitoringMode::Live)
        ->and($control->generation)->toBe(1)
        ->and($control->binding->is($binding))->toBeTrue();

    $replayed = app(SetCampaignPaymentMonitoring::class)->handle(
        binding: $binding,
        mode: CampaignPaymentMonitoringMode::Live,
        expectedGeneration: 1,
        reason: 'operator_started',
        actorType: $owner->getMorphClass(),
        actorId: (string) $owner->getKey(),
    );

    expect($replayed->generation)->toBe(1)
        ->and(CampaignPaymentMonitoringControl::query()->count())->toBe(1)
        ->and(fn () => app(SetCampaignPaymentMonitoring::class)->handle(
            binding: $binding,
            mode: CampaignPaymentMonitoringMode::Paused,
            expectedGeneration: 0,
            reason: 'stale_operator_request',
            actorType: $owner->getMorphClass(),
            actorId: (string) $owner->getKey(),
        ))->toThrow(LogicException::class, 'generation is stale');

    $campaign->forceFill(['status' => 'paused'])->save();

    $paused = app(SetCampaignPaymentMonitoring::class)->handle(
        binding: $binding,
        mode: CampaignPaymentMonitoringMode::Paused,
        expectedGeneration: 1,
        reason: 'campaign_paused',
        actorType: $owner->getMorphClass(),
        actorId: (string) $owner->getKey(),
    );

    expect($paused->mode)->toBe(CampaignPaymentMonitoringMode::Paused)
        ->and($paused->generation)->toBe(2)
        ->and(fn () => app(SetCampaignPaymentMonitoring::class)->handle(
            binding: $binding,
            mode: CampaignPaymentMonitoringMode::Live,
            expectedGeneration: 2,
            reason: 'invalid_resume',
            actorType: $owner->getMorphClass(),
            actorId: (string) $owner->getKey(),
        ))->toThrow(DomainException::class, 'campaign_not_active');
});

it('queues only explicitly live campaign payment addresses and replay is mutation-free', function (): void {
    Bus::fake();
    config([
        'x-change.funding.standing_addresses.enabled' => true,
        'x-change.funding.providers.netbank.enabled' => true,
        'x-change.campaigns.payment_monitoring.scheduled_sync_enabled' => true,
        'x-change.campaigns.payment_monitoring.scheduled_batch_size' => 1,
        'x-change.campaigns.payment_monitoring.scheduled_minimum_interval_seconds' => 60,
    ]);
    [$owner, , $liveBinding, $liveAddress] = monitoredCampaignFixture();
    [, , , $pausedAddress] = monitoredCampaignFixture();
    monitoredAccountFundingAddress();
    StandingFundingRuntimeControl::query()->create([
        'provider_code' => 'netbank',
        'mode' => StandingFundingRuntimeMode::Scheduled,
        'generation' => 9,
        'batch_limit' => 1,
        'backlog_ceiling' => 1,
        'last_transition' => 'test_campaign_schedule',
        'transitioned_at' => now(),
    ]);
    app(SetCampaignPaymentMonitoring::class)->handle(
        binding: $liveBinding,
        mode: CampaignPaymentMonitoringMode::Live,
        expectedGeneration: 0,
        reason: 'operator_started',
        actorType: $owner->getMorphClass(),
        actorId: (string) $owner->getKey(),
    );

    $this->artisan('xchange:campaigns:sync-payment-addresses', [
        '--provider' => 'netbank',
        '--limit' => 10,
    ])->assertSuccessful()->expectsOutputToContain('Queued 1 campaign payment synchronization check(s).');

    Bus::assertDispatchedTimes(SyncStandingFundingAddressJob::class, 1);
    Bus::assertDispatched(
        SyncStandingFundingAddressJob::class,
        fn (SyncStandingFundingAddressJob $job): bool => $job->standingFundingAddressId === $liveAddress->getKey()
            && $job->trigger === 'campaign_schedule'
            && $job->runtimeGeneration === 9,
    );
    Bus::assertNotDispatched(
        SyncStandingFundingAddressJob::class,
        fn (SyncStandingFundingAddressJob $job): bool => $job->standingFundingAddressId === $pausedAddress->getKey(),
    );

    $this->artisan('xchange:campaigns:sync-payment-addresses', [
        '--provider' => 'netbank',
        '--limit' => 10,
    ])->assertSuccessful()->expectsOutputToContain('Queued 0 campaign payment synchronization check(s).');

    Bus::assertDispatchedTimes(SyncStandingFundingAddressJob::class, 1);
});

it('registers only the separately enabled campaign payment schedule', function (): void {
    config([
        'x-change.funding.standing_addresses.enabled' => true,
        'x-change.funding.standing_addresses.scheduled_sync_enabled' => false,
        'x-change.campaigns.payment_monitoring.scheduled_sync_enabled' => true,
        'x-change.campaigns.payment_monitoring.scheduled_batch_size' => 1,
    ]);

    $events = collect(app(Schedule::class)->events());
    $campaignEvent = $events->first(
        fn ($event): bool => $event->description === 'xchange:campaigns:sync-payment-addresses:netbank',
    );

    expect($campaignEvent)->not->toBeNull()
        ->and($campaignEvent->expression)->toBe('* * * * *')
        ->and($campaignEvent->withoutOverlapping)->toBeTrue()
        ->and($campaignEvent->onOneServer)->toBeTrue()
        ->and($campaignEvent->expiresAt)->toBe(5)
        ->and($campaignEvent->command)->toContain('xchange:campaigns:sync-payment-addresses --provider=netbank --limit=1')
        ->and($campaignEvent->description)->not->toBe('xchange:funding:sync-standing:netbank');
});

/**
 * @return array{mixed, LeadCampaign, CampaignPaymentQrBinding, StandingFundingAddress}
 */
function monitoredCampaignFixture(): array
{
    $owner = actingAsTestUser();
    $template = PayCodeTemplate::query()->create([
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => (string) $owner->getKey(),
        'name' => 'Monitoring template '.Str::ulid(),
        'base_template_key' => 'blank-pay-code',
        'instructions_ciphertext' => ['cash' => ['amount' => 0, 'currency' => 'PHP']],
        'include_amount' => true,
        'include_purpose' => true,
        'status' => 'active',
    ]);
    $campaign = LeadCampaign::query()->create([
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => (string) $owner->getKey(),
        'pay_code_template_id' => $template->getKey(),
        'active_template_version_id' => 'revision-'.Str::ulid(),
        'merchant_display_name' => 'AUI Insurance',
        'merchant_slug' => 'aui-'.strtolower((string) Str::ulid()),
        'endpoint_slug' => 'monitoring-'.strtolower((string) Str::ulid()),
        'title' => 'AUI monitored campaign',
        'status' => 'active',
        'settings' => ['entry_mode' => CampaignEntryMode::ReusablePaymentQr->value],
    ]);
    $address = monitoredPaymentAddress($owner, FundingAddressPurpose::Payment);
    $artifact = $address->qrArtifacts()->create([
        'status' => 'active',
        'version' => 1,
        'artifact_fingerprint' => hash('sha256', 'artifact-'.$address->reference),
        'mime_type' => 'image/png',
        'qr_mode' => 'Static',
        'transaction_type' => 'P2M',
        'embedded_amount' => false,
        'provider_generated' => true,
        'payload_ciphertext' => base64_encode('synthetic-monitoring-qr'),
        'display_snapshot_ciphertext' => ['provider' => 'netbank'],
        'generated_at' => now(),
    ]);
    $binding = CampaignPaymentQrBinding::query()->create([
        'endpoint_campaign_id' => $campaign->getKey(),
        'campaign_revision_id' => $campaign->active_template_version_id,
        'standing_funding_address_id' => $address->getKey(),
        'standing_funding_qr_artifact_id' => $artifact->getKey(),
        'entry_mode' => CampaignEntryMode::ReusablePaymentQr,
        'provider_code' => 'netbank',
        'currency' => 'PHP',
        'amount_mode' => CampaignPaymentAmountMode::Fixed,
        'fixed_amount_minor' => 5_000,
        'available_from' => now()->subMinute(),
        'available_until' => now()->addDay(),
        'permitted_payment_rules' => ['allowed_rails' => ['INSTAPAY']],
        'configuration_hash' => hash('sha256', 'binding-'.$campaign->reference),
    ]);

    return [$owner, $campaign, $binding, $address];
}

function monitoredAccountFundingAddress(): StandingFundingAddress
{
    return monitoredPaymentAddress(actingAsTestUser(), FundingAddressPurpose::AccountFunding);
}

function monitoredPaymentAddress(mixed $owner, FundingAddressPurpose $purpose): StandingFundingAddress
{
    $reference = (string) Str::ulid();

    return StandingFundingAddress::query()->create([
        'binding_key' => hash('sha256', 'monitoring-binding-'.$reference),
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => (string) $owner->getKey(),
        'account_reference' => 'campaign:'.$owner->getKey().':'.$reference,
        'provider_code' => 'netbank',
        'purpose' => $purpose,
        'recognition_mode' => FundingRecognitionMode::ObserveOnly,
        'status' => FundingAddressStatus::Active,
        'version' => 1,
        'provider_reference' => 'standing:netbank:'.$reference,
        'funding_address_ciphertext' => '91500'.$reference,
        'funding_address_hash' => hash('sha256', 'address-'.$reference),
        'currency' => 'PHP',
        'activated_at' => now(),
    ]);
}
