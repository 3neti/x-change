<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use LBHurtado\XChange\Tests\Fakes\User;
use LBHurtado\XProvisioning\Models\ProvisioningEvent;
use LBHurtado\XProvisioning\Models\ProvisioningRequest;
use LBHurtado\XProvisioning\Models\ProvisioningSeat;

function provisioningSimulationActor(string $name, string $mobile): User
{
    $actor = User::query()->create([
        'name' => $name,
        'email' => str($name)->slug().'-'.str()->uuid().'@example.test',
        'password' => 'not-a-login-credential',
    ]);
    $actor->setMobileChannel($mobile)->save();

    return $actor;
}

it('simulates governed provisioning and rolls the complete envelope back', function (): void {
    Http::preventStrayRequests();
    enableNetbankTreasuryForTests();
    config()->set('x-change.lifecycle.defaults.user_model', User::class);
    config()->set('x-change.lifecycle.provisioning_governance_simulation.enabled', true);
    $candidate = provisioningSimulationActor('Provisioning Candidate', '09171120001');
    $maker = provisioningSimulationActor('Provisioning Maker', '09171120002');
    $checker = provisioningSimulationActor('Provisioning Checker', '09171120003');
    $before = [
        'requests' => ProvisioningRequest::query()->count(),
        'seats' => ProvisioningSeat::query()->count(),
        'events' => ProvisioningEvent::query()->count(),
    ];

    $exitCode = Artisan::call('xchange:lifecycle:run', [
        'scenario' => 'provisioning_governance_simulation',
        '--issuer' => (string) $candidate->getKey(),
        '--maker' => (string) $maker->getKey(),
        '--checker' => (string) $checker->getKey(),
        '--json' => true,
    ]);
    $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    $this->assertSame(0, $exitCode, json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

    expect(data_get($payload, 'schema'))->toBe('x-change.lifecycle.provisioning-governance-simulation.v1')
        ->and(data_get($payload, 'success'))->toBeTrue()
        ->and(data_get($payload, 'persisted'))->toBeFalse()
        ->and(data_get($payload, 'rollback_completed'))->toBeTrue()
        ->and(data_get($payload, 'safety.provider_calls'))->toBeFalse()
        ->and(data_get($payload, 'safety.real_money_movement'))->toBeFalse()
        ->and(data_get($payload, 'safety.domain_authority_granted'))->toBeFalse()
        ->and(data_get($payload, 'envelope.status'))->toBe('activation_pending')
        ->and(data_get($payload, 'stages.5.result'))->toBe('fail_closed_pending_explicit_adapter')
        ->and(collect(data_get($payload, 'invariants'))->every(static fn (bool $passed): bool => $passed))->toBeTrue()
        ->and(ProvisioningRequest::query()->count())->toBe($before['requests'])
        ->and(ProvisioningSeat::query()->count())->toBe($before['seats'])
        ->and(ProvisioningEvent::query()->count())->toBe($before['events']);
});
