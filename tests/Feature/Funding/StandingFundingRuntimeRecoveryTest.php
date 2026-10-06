<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use LBHurtado\EmiCore\Enums\FundingAddressPurpose;
use LBHurtado\XChange\Contracts\AppendableEventStoreContract;
use LBHurtado\XChange\Enums\FundingAddressStatus;
use LBHurtado\XChange\Enums\FundingRecognitionMode;
use LBHurtado\XChange\Enums\StandingFundingRuntimeMode;
use LBHurtado\XChange\Events\StandingFundingRuntimeChanged;
use LBHurtado\XChange\Models\StandingFundingAddress;
use LBHurtado\XChange\Models\StandingFundingAddressState;
use LBHurtado\XChange\Models\StandingFundingRuntimeControl;
use LBHurtado\XChange\Models\StandingFundingRuntimeOutbox;
use LBHurtado\XChange\Models\StandingFundingSyncRun;
use LBHurtado\XChange\Services\Funding\StandingFundingAddressRecovery;
use LBHurtado\XChange\Services\Funding\StandingFundingRuntimeChannel;
use LBHurtado\XChange\Services\Funding\StandingFundingRuntimeManager;
use LBHurtado\XChange\Services\Funding\StandingFundingRuntimeOutboxProcessor;
use LBHurtado\XChange\Services\Funding\StandingFundingSyncAdmission;
use LBHurtado\XChange\Services\Funding\StandingFundingSyncRuntime;

it('opens the provider circuit immediately for database resource exhaustion', function () {
    $address = recoveryStandingAddress();
    recoveryRuntimeControl(StandingFundingRuntimeMode::Scheduled);
    $decision = app(StandingFundingSyncAdmission::class)->admit($address, 'schedule');
    $runtime = app(StandingFundingSyncRuntime::class);

    expect($runtime->start($decision->runReference, $decision->leaseToken, $decision->generation))->toBeTrue();
    $classification = $runtime->fail($decision->runReference, new RuntimeException('SQLSTATE[53200]: Out of memory'));

    expect($classification->value)->toBe('database_resource_exhausted')
        ->and(StandingFundingRuntimeControl::query()->sole()->mode)->toBe(StandingFundingRuntimeMode::CircuitOpen)
        ->and(StandingFundingAddressState::query()->sole()->status->value)->toBe('cooldown')
        ->and(StandingFundingSyncRun::query()->sole()->status->value)->toBe('failed')
        ->and(StandingFundingRuntimeOutbox::query()->where('event_type', 'standing_funding.sync.resource_exhausted')->exists())->toBeTrue();
});

it('falls back to a sanitized log when failure evidence cannot be persisted', function () {
    $database = app('db');
    Log::shouldReceive('critical')->once()->with(
        'Standing Funding runtime failure evidence could not be persisted.',
        Mockery::on(fn (array $context): bool => $context['failure_classification'] === 'provider_transient'
            && $context['persistence_failure_type'] === 'RuntimeException'
            && $context['run_reference_hash'] === hash('sha256', 'run-database-down')),
    );
    DB::shouldReceive('transaction')->once()->andThrow(new RuntimeException('database unavailable'));

    $classification = app(StandingFundingSyncRuntime::class)->fail(
        'run-database-down',
        new RuntimeException('provider temporarily unavailable'),
        false,
    );
    app()->instance('db', $database);
    DB::clearResolvedInstance('db');

    expect($classification->value)->toBe('provider_transient');
});

it('projects journal and broadcast independently and replay is mutation free', function () {
    Event::fake([StandingFundingRuntimeChanged::class]);
    $journal = Mockery::mock(AppendableEventStoreContract::class);
    $journal->shouldReceive('append')->once();
    app()->instance(AppendableEventStoreContract::class, $journal);
    StandingFundingRuntimeOutbox::query()->create([
        'reference' => (string) Str::ulid(),
        'event_type' => 'standing_funding.runtime.mode_changed',
        'aggregate_type' => 'standing_funding_runtime',
        'aggregate_id' => 'netbank',
        'generation' => 2,
        'payload' => ['provider_code' => 'netbank', 'mode' => 'canary'],
        'occurred_at' => now(),
    ]);
    $processor = app(StandingFundingRuntimeOutboxProcessor::class);

    expect($processor->process())->toBe(1)
        ->and($processor->process())->toBe(0);
    $event = StandingFundingRuntimeOutbox::query()->sole();
    expect($event->journal_status)->toBe('delivered')
        ->and($event->broadcast_status)->toBe('delivered')
        ->and($event->journal_attempts)->toBe(1)
        ->and($event->broadcast_attempts)->toBe(1);
    Event::assertDispatchedTimes(StandingFundingRuntimeChanged::class, 1);
});

it('keeps journal failure pending without preventing the broadcast projection', function () {
    Event::fake([StandingFundingRuntimeChanged::class]);
    $journal = Mockery::mock(AppendableEventStoreContract::class);
    $journal->shouldReceive('append')->once()->andThrow(new RuntimeException('journal unavailable'));
    app()->instance(AppendableEventStoreContract::class, $journal);
    StandingFundingRuntimeOutbox::query()->create([
        'reference' => (string) Str::ulid(),
        'event_type' => 'standing_funding.runtime.circuit_opened',
        'aggregate_type' => 'standing_funding_runtime',
        'aggregate_id' => 'netbank',
        'generation' => 2,
        'payload' => ['provider_code' => 'netbank', 'mode' => 'circuit_open'],
        'occurred_at' => now(),
    ]);

    app(StandingFundingRuntimeOutboxProcessor::class)->process();
    $event = StandingFundingRuntimeOutbox::query()->sole();
    expect($event->journal_status)->toBe('pending')
        ->and($event->broadcast_status)->toBe('delivered')
        ->and($event->journal_error)->toBe('RuntimeException')
        ->and($event->journal_error)->not->toContain('journal unavailable');
});

it('broadcasts only an opaque invalidation payload', function () {
    $event = new StandingFundingRuntimeChanged(
        '01KTESTEVENT00000000000000',
        'standing_funding.sync.resource_exhausted',
        '2026-10-06T12:00:00+08:00',
    );

    expect($event->broadcastWith())->toBe([
        'schema' => 'x-change.standing-funding-runtime-changed.v1',
        'event_id' => hash('sha256', '01KTESTEVENT00000000000000'),
        'reason' => 'standing_funding.sync.resource_exhausted',
        'occurred_at' => '2026-10-06T12:00:00+08:00',
    ])->not->toHaveKeys(['provider_code', 'amount', 'account_reference', 'funding_address']);
});

it('authorizes the private runtime channel with an instance scoped keyed token', function () {
    config([
        'x-change.instance.id' => 'testing-instance',
        'x-change.funding.broadcast_reference_hash_key' => 'testing-broadcast-key',
    ]);
    $user = actingAsTestUser(0);
    $channel = app(StandingFundingRuntimeChannel::class);
    $token = hash_hmac('sha256', 'testing-instance', 'testing-broadcast-key');

    expect($channel->name())->toBe('x-change.standing-funding.'.$token)
        ->and($channel->authorizes($user, $token))->toBeTrue()
        ->and($channel->authorizes($user, hash('sha256', 'testing-instance')))->toBeFalse();
});

it('previews transitions and requires generation-fenced apply', function () {
    recoveryRuntimeControl(StandingFundingRuntimeMode::Disabled);
    $address = recoveryStandingAddress();

    $this->artisan('xchange:funding:standing-runtime:transition', ['mode' => 'canary', '--generation' => 1, '--address' => $address->getKey()])
        ->assertSuccessful();
    expect(StandingFundingRuntimeControl::query()->sole()->mode)->toBe(StandingFundingRuntimeMode::Disabled);

    $this->artisan('xchange:funding:standing-runtime:transition', ['mode' => 'canary', '--generation' => 1, '--address' => $address->getKey(), '--apply' => true])
        ->assertSuccessful();
    expect(StandingFundingRuntimeControl::query()->sole()->mode)->toBe(StandingFundingRuntimeMode::Canary)
        ->and(StandingFundingRuntimeControl::query()->sole()->generation)->toBe(2);
});

it('exposes preview-first recovery commands and status does not create runtime state', function () {
    $this->artisan('xchange:funding:standing-runtime:status')->assertSuccessful();
    expect(StandingFundingRuntimeControl::query()->count())->toBe(0);

    $commands = array_keys(Artisan::all());
    expect($commands)->toContain(
        'xchange:funding:standing-runtime:pause',
        'xchange:funding:standing-runtime:drain',
        'xchange:funding:standing-runtime:quarantine-address',
        'xchange:funding:standing-runtime:release-stale-lease',
        'xchange:funding:standing-runtime:reconcile-ambiguous',
        'xchange:funding:standing-runtime:retry',
        'xchange:funding:standing-runtime:resume-canary',
        'xchange:funding:standing-runtime:promote',
    );
});

it('runs the disabled canary failure reconciliation and bounded recovery lifecycle', function () {
    $operator = actingAsTestUser(0);
    config()->set('x-change.legal.eula.enabled', false);
    $address = recoveryStandingAddress();
    $runtime = app(StandingFundingRuntimeManager::class);
    $control = $runtime->control('netbank');
    $admission = app(StandingFundingSyncAdmission::class);
    expect($admission->admit($address, 'schedule')->admitted)->toBeFalse();

    $control = $runtime->transition('netbank', StandingFundingRuntimeMode::Canary, 'canary_start', 'operator', 'tester', $address->getKey(), $control->generation);
    $decision = $admission->admit($address, 'operator');
    $sync = app(StandingFundingSyncRuntime::class);
    expect($sync->start($decision->runReference, $decision->leaseToken, $decision->generation))->toBeTrue();
    $sync->fail($decision->runReference, new RuntimeException('provider timed out after request'));
    expect(StandingFundingAddressState::query()->sole()->status->value)->toBe('ambiguous');
    $response = $this->actingAs($operator)
        ->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.pay-codes.index'));
    expect($response->status())->toBeLessThan(500);

    app(StandingFundingAddressRecovery::class)->apply($address->getKey(), 'reconcile-ambiguous', $control->generation, 'checker', 'provider_truth_confirmed_no_effect');
    expect(StandingFundingAddressState::query()->sole()->status->value)->toBe('idle');

    $control = $runtime->transition('netbank', StandingFundingRuntimeMode::Bounded, 'canary_accepted', 'operator', 'checker', expectedGeneration: $control->generation);
    $first = $admission->admit($address, 'schedule');
    $counts = [StandingFundingSyncRun::query()->count(), StandingFundingRuntimeOutbox::query()->count()];
    $rerun = $admission->admit($address, 'schedule');
    expect($first->admitted)->toBeTrue()
        ->and($rerun->admitted)->toBeFalse()
        ->and([StandingFundingSyncRun::query()->count(), StandingFundingRuntimeOutbox::query()->count()])->toBe($counts)
        ->and($control->mode)->toBe(StandingFundingRuntimeMode::Bounded);
});

function recoveryRuntimeControl(StandingFundingRuntimeMode $mode): StandingFundingRuntimeControl
{
    return StandingFundingRuntimeControl::query()->create([
        'provider_code' => 'netbank', 'mode' => $mode, 'generation' => 1,
        'batch_limit' => 10, 'backlog_ceiling' => 10,
        'last_transition' => 'test_fixture', 'transitioned_at' => now(),
    ]);
}

function recoveryStandingAddress(): StandingFundingAddress
{
    return StandingFundingAddress::query()->create([
        'binding_key' => hash('sha256', fake()->uuid()),
        'account_reference' => 'wallet:recovery-account',
        'provider_code' => 'netbank',
        'purpose' => FundingAddressPurpose::AccountFunding,
        'recognition_mode' => FundingRecognitionMode::ObserveOnly,
        'status' => FundingAddressStatus::Active,
        'version' => 1,
        'provider_reference' => 'standing:netbank:recovery',
        'funding_address_ciphertext' => '915001234567890123456',
        'funding_address_hash' => hash('sha256', fake()->uuid()),
        'currency' => 'PHP',
        'activated_at' => now(),
    ]);
}
