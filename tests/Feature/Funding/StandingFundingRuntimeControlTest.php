<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Bus;
use LBHurtado\EmiCore\Enums\FundingAddressPurpose;
use LBHurtado\XChange\Enums\FundingAddressStatus;
use LBHurtado\XChange\Enums\FundingRecognitionMode;
use LBHurtado\XChange\Enums\StandingFundingRuntimeMode;
use LBHurtado\XChange\Jobs\Funding\SyncStandingFundingAddressJob;
use LBHurtado\XChange\Models\StandingFundingAddress;
use LBHurtado\XChange\Models\StandingFundingAddressState;
use LBHurtado\XChange\Models\StandingFundingRuntimeControl;
use LBHurtado\XChange\Models\StandingFundingSyncRun;
use LBHurtado\XChange\Services\Funding\StandingFundingRuntimeManager;
use LBHurtado\XChange\Services\Funding\StandingFundingSyncAdmission;
use LBHurtado\XChange\Services\Funding\StandingFundingSyncRuntime;

it('fails closed when no runtime control exists', function () {
    $address = runtimeStandingAddress();

    $decision = app(StandingFundingSyncAdmission::class)->admit($address, 'schedule');

    expect($decision->admitted)->toBeFalse()
        ->and($decision->reason)->toBe('runtime_disabled')
        ->and(StandingFundingSyncRun::query()->count())->toBe(0)
        ->and(StandingFundingAddressState::query()->count())->toBe(0);
});

it('admits only one lease and makes an immediate rerun mutation free', function () {
    $address = runtimeStandingAddress();
    runtimeControl(StandingFundingRuntimeMode::Scheduled);
    $admission = app(StandingFundingSyncAdmission::class);

    $first = $admission->admit($address, 'schedule');
    $counts = [StandingFundingSyncRun::query()->count(), StandingFundingAddressState::query()->count()];
    $second = $admission->admit($address->refresh(), 'webhook', 99);

    expect($first->admitted)->toBeTrue()
        ->and($second->admitted)->toBeFalse()
        ->and($second->reason)->toBe('lease_active')
        ->and([StandingFundingSyncRun::query()->count(), StandingFundingAddressState::query()->count()])->toBe($counts);
});

it('rejects a duplicate delivery after the run has started', function () {
    $address = runtimeStandingAddress();
    runtimeControl(StandingFundingRuntimeMode::Scheduled);
    $decision = app(StandingFundingSyncAdmission::class)->admit($address, 'schedule');
    $runtime = app(StandingFundingSyncRuntime::class);

    expect($runtime->start($decision->runReference, $decision->leaseToken, $decision->generation))->toBeTrue()
        ->and($runtime->start($decision->runReference, $decision->leaseToken, $decision->generation))->toBeFalse()
        ->and(StandingFundingSyncRun::query()->sole()->status->value)->toBe('running')
        ->and(StandingFundingAddressState::query()->sole()->status->value)->toBe('running');
});

it('fences queued work after an operator pause increments the generation', function () {
    $address = runtimeStandingAddress();
    runtimeControl(StandingFundingRuntimeMode::Scheduled);
    $decision = app(StandingFundingSyncAdmission::class)->admit($address, 'schedule');

    app(StandingFundingRuntimeManager::class)->transition('netbank', StandingFundingRuntimeMode::Paused, 'operator_pause', 'operator', 'tester', expectedGeneration: 1);
    app()->call([new SyncStandingFundingAddressJob(
        $address->getKey(), 'netbank', 'schedule', null,
        $decision->generation, $decision->runReference, $decision->leaseToken,
    ), 'handle']);

    expect(StandingFundingSyncRun::query()->sole()->status->value)->toBe('stale')
        ->and($address->refresh()->last_checked_at)->toBeNull();
});

it('drains current generation work while rejecting new admissions', function () {
    $address = runtimeStandingAddress();
    runtimeControl(StandingFundingRuntimeMode::Scheduled);
    $decision = app(StandingFundingSyncAdmission::class)->admit($address, 'schedule');

    $control = app(StandingFundingRuntimeManager::class)->transition(
        'netbank',
        StandingFundingRuntimeMode::Draining,
        'operator_drain',
        'operator',
        'tester',
        expectedGeneration: 1,
    );
    $second = app(StandingFundingSyncAdmission::class)->admit($address->refresh(), 'schedule');

    expect($control->generation)->toBe(2)
        ->and($second->admitted)->toBeFalse()
        ->and(app(StandingFundingSyncRuntime::class)->start(
            $decision->runReference,
            $decision->leaseToken,
            $decision->generation,
        ))->toBeTrue()
        ->and(StandingFundingSyncRun::query()->sole()->status->value)->toBe('running');
});

it('the scheduler cannot redispatch an address with a current lease', function () {
    Bus::fake();
    config([
        'x-change.funding.standing_addresses.enabled' => true,
        'x-change.funding.providers.netbank.enabled' => true,
    ]);
    runtimeControl(StandingFundingRuntimeMode::Scheduled);
    runtimeStandingAddress();

    $this->artisan('xchange:funding:sync-standing', ['--provider' => 'netbank'])->assertSuccessful();
    $this->artisan('xchange:funding:sync-standing', ['--provider' => 'netbank'])->assertSuccessful();

    Bus::assertDispatchedTimes(SyncStandingFundingAddressJob::class, 1);
    expect(StandingFundingSyncRun::query()->count())->toBe(1);
});

function runtimeControl(StandingFundingRuntimeMode $mode): StandingFundingRuntimeControl
{
    return StandingFundingRuntimeControl::query()->create([
        'provider_code' => 'netbank', 'mode' => $mode, 'generation' => 1,
        'batch_limit' => 10, 'backlog_ceiling' => 10,
        'last_transition' => 'test_fixture', 'transitioned_at' => now(),
    ]);
}

function runtimeStandingAddress(): StandingFundingAddress
{
    return StandingFundingAddress::query()->create([
        'binding_key' => hash('sha256', fake()->uuid()),
        'account_reference' => 'wallet:runtime-account',
        'provider_code' => 'netbank',
        'purpose' => FundingAddressPurpose::AccountFunding,
        'recognition_mode' => FundingRecognitionMode::ObserveOnly,
        'status' => FundingAddressStatus::Active,
        'version' => 1,
        'provider_reference' => 'standing:netbank:runtime',
        'funding_address_ciphertext' => '915001234567890123456',
        'funding_address_hash' => hash('sha256', fake()->uuid()),
        'currency' => 'PHP',
        'activated_at' => now(),
    ]);
}
