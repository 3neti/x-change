<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use LBHurtado\XChange\Models\TreasuryAccountGrant;
use LBHurtado\XChange\Models\TreasuryOperatorAuthorization;
use LBHurtado\XChange\Tests\Fakes\User;

function treasuryGrantSimulationActor(string $name, string $mobile): User
{
    $actor = User::query()->create([
        'name' => $name,
        'email' => str($name)->slug().'-'.str()->uuid().'@example.test',
        'password' => 'not-a-login-credential',
    ]);
    $actor->setMobileChannel($mobile);
    $actor->save();

    return $actor;
}

it('simulates a maker-checker Account Grant and rolls all accounting back', function (): void {
    Http::preventStrayRequests();
    enableNetbankTreasuryForTests();
    config()->set('x-change.lifecycle.defaults.user_model', User::class);
    config()->set('x-change.lifecycle.treasury_account_grant_simulation.enabled', true);
    $issuer = treasuryGrantSimulationActor('Grant Recipient', '09171110001');
    $maker = treasuryGrantSimulationActor('Grant Maker', '09171110002');
    $checker = treasuryGrantSimulationActor('Grant Checker', '09171110003');
    $before = [
        'grants' => TreasuryAccountGrant::query()->count(),
        'authorizations' => TreasuryOperatorAuthorization::query()->count(),
        'position_operations' => DB::table('treasury_position_operations')->count(),
        'inventory_operations' => DB::table('treasury_inventory_operations')->count(),
    ];

    $exitCode = Artisan::call('xchange:lifecycle:run', [
        'scenario' => 'treasury_account_grant_simulation',
        '--issuer' => (string) $issuer->getKey(),
        '--maker' => (string) $maker->getKey(),
        '--checker' => (string) $checker->getKey(),
        '--amount' => '100',
        '--json' => true,
    ]);
    $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($exitCode)->toBe(0)
        ->and(data_get($payload, 'schema'))->toBe('x-change.lifecycle.treasury-account-grant-simulation.v1')
        ->and(data_get($payload, 'success'))->toBeTrue()
        ->and(data_get($payload, 'persisted'))->toBeFalse()
        ->and(data_get($payload, 'rollback_completed'))->toBeTrue()
        ->and(data_get($payload, 'safety.provider_calls'))->toBeFalse()
        ->and(data_get($payload, 'safety.real_money_movement'))->toBeFalse()
        ->and(data_get($payload, 'grant.amount_minor'))->toBe(10_000)
        ->and(collect(data_get($payload, 'invariants'))->every(static fn (bool $passed): bool => $passed))->toBeTrue()
        ->and(TreasuryAccountGrant::query()->count())->toBe($before['grants'])
        ->and(TreasuryOperatorAuthorization::query()->count())->toBe($before['authorizations'])
        ->and(DB::table('treasury_position_operations')->count())->toBe($before['position_operations'])
        ->and(DB::table('treasury_inventory_operations')->count())->toBe($before['inventory_operations']);
});
