<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use LBHurtado\XAffiliation\Models\AffiliationMembership;
use LBHurtado\XAffiliation\Models\AffiliationPath;
use LBHurtado\XAffiliation\Models\AffiliationSponsorship;
use LBHurtado\XChange\Models\AffiliationInvitationAuthority;
use LBHurtado\XChange\Tests\Fakes\User;

function affiliationSimulationActor(string $name, string $mobile): User
{
    $actor = User::query()->create([
        'name' => $name,
        'email' => str($name)->slug().'-'.str()->uuid().'@example.test',
        'password' => 'not-a-login-credential',
    ]);
    $actor->setMobileChannel($mobile)->forceFill(['mobile_verified_at' => now()]);
    $actor->save();

    return $actor;
}

it('simulates two-generation affiliation networking and rolls every artifact back', function (): void {
    Http::preventStrayRequests();
    enableNetbankTreasuryForTests();
    config()->set('x-change.lifecycle.defaults.user_model', User::class);
    config()->set('x-change.lifecycle.affiliation_networking_simulation.enabled', true);
    config()->set('x-change.affiliation.enabled', true);
    config()->set('x-change.instance.id', 'affiliation-lifecycle-simulation');
    config()->set('x-affiliation.identity_pepper', 'affiliation-lifecycle-simulation-pepper');

    $sponsor = affiliationSimulationActor('Alice Sponsor', '09173011987');
    $checker = affiliationSimulationActor('Independent Checker', '09170000002');
    $sponsor->wallet()->firstOrCreate(['slug' => 'platform'], ['name' => 'Platform Wallet']);
    $before = [
        'memberships' => AffiliationMembership::query()->count(),
        'sponsorships' => AffiliationSponsorship::query()->count(),
        'paths' => AffiliationPath::query()->count(),
        'authorities' => AffiliationInvitationAuthority::query()->count(),
    ];

    $exitCode = Artisan::call('xchange:lifecycle:run', [
        'scenario' => 'affiliation_networking_simulation',
        '--issuer' => (string) $sponsor->getKey(),
        '--maker' => (string) $sponsor->getKey(),
        '--checker' => (string) $checker->getKey(),
        '--json' => true,
    ]);
    $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    $this->assertSame(0, $exitCode, json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

    expect(data_get($payload, 'schema'))->toBe('x-change.lifecycle.affiliation-networking-simulation.v1')
        ->and(data_get($payload, 'success'))->toBeTrue()
        ->and(data_get($payload, 'persisted'))->toBeFalse()
        ->and(data_get($payload, 'rollback_completed'))->toBeTrue()
        ->and(data_get($payload, 'safety.provider_calls'))->toBeFalse()
        ->and(data_get($payload, 'safety.real_money_movement'))->toBeFalse()
        ->and(data_get($payload, 'safety.raw_mobile_output'))->toBeFalse()
        ->and(data_get($payload, 'graph.membership_count'))->toBe(3)
        ->and(data_get($payload, 'graph.direct_sponsorship_count'))->toBe(2)
        ->and(data_get($payload, 'graph.relationships'))->toBe([
            ['from' => 'Alice', 'to' => 'Bob', 'depth' => 1],
            ['from' => 'Bob', 'to' => 'Carol', 'depth' => 1],
            ['from' => 'Alice', 'to' => 'Carol', 'depth' => 2],
        ])
        ->and(collect(data_get($payload, 'invariants'))->every(static fn (bool $passed): bool => $passed))->toBeTrue()
        ->and(json_encode($payload, JSON_THROW_ON_ERROR))->not->toContain('09175180722')
        ->and(json_encode($payload, JSON_THROW_ON_ERROR))->not->toContain('09171234567')
        ->and(AffiliationMembership::query()->count())->toBe($before['memberships'])
        ->and(AffiliationSponsorship::query()->count())->toBe($before['sponsorships'])
        ->and(AffiliationPath::query()->count())->toBe($before['paths'])
        ->and(AffiliationInvitationAuthority::query()->count())->toBe($before['authorities']);
});
