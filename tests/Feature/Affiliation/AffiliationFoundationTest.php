<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use LBHurtado\XAffiliation\Exceptions\AffiliationConflict;
use LBHurtado\XAffiliation\Models\AffiliationMembership;
use LBHurtado\XAffiliation\Models\AffiliationNetwork;
use LBHurtado\XChange\Actions\Affiliation\EnrollAffiliationRootAccount;
use LBHurtado\XChange\Services\Affiliation\AffiliationInstallationNetwork;
use LBHurtado\XChange\Services\Configuration\AffiliationReadinessInspector;
use LBHurtado\XChange\Tests\Fakes\User;

beforeEach(function (): void {
    config()->set('x-change.affiliation.enabled', true);
    config()->set('x-change.instance.id', 'installation-affiliation-test');
    config()->set('x-affiliation.identity_pepper', 'stable-affiliation-test-pepper');
});

it('ensures one stable application network across repeated commissioning', function (): void {
    $service = app(AffiliationInstallationNetwork::class);

    $first = $service->ensure();
    $second = $service->ensure();

    expect($first)->not->toBeNull()
        ->and($second?->is($first))->toBeTrue()
        ->and(AffiliationNetwork::query()->count())->toBe(1)
        ->and($first?->scope_reference)->toBe('installation-affiliation-test');
});

it('fails readiness closed when enabled identity configuration is incomplete', function (): void {
    config()->set('x-change.instance.id', null);
    config()->set('x-affiliation.identity_pepper', null);

    $readiness = app(AffiliationReadinessInspector::class)->inspect(requireNetwork: false);

    expect($readiness['passed'])->toBeFalse()
        ->and($readiness['meta']['missing_variables'])->toBe([
            'XCHANGE_INSTANCE_ID',
            'X_AFFILIATION_IDENTITY_PEPPER',
        ]);
});

it('adopts a verified Account root idempotently without financial mutation', function (): void {
    $account = (new User)->forceFill([
        'name' => 'Alice Root',
        'email' => 'alice-root@example.test',
        'mobile' => '09173011987',
        'mobile_verified_at' => now(),
        'password' => bcrypt('unused-password'),
    ]);
    $account->save();
    $account->wallet()->firstOrCreate(
        ['slug' => 'platform'],
        ['name' => 'Platform Wallet'],
    );
    $walletTransactionsBefore = DB::table('transactions')->count();

    $first = app(EnrollAffiliationRootAccount::class)->handle(
        $account,
        'commissioning:root:alice:v1',
    );
    $second = app(EnrollAffiliationRootAccount::class)->handle(
        $account,
        'commissioning:root:alice:v1',
    );

    expect($second->is($first))->toBeTrue()
        ->and(AffiliationMembership::query()->count())->toBe(1)
        ->and($first->identity_key)->toMatch('/^[a-f0-9]{64}$/')
        ->and(DB::table('transactions')->count())->toBe($walletTransactionsBefore);
});

it('rejects silent root re-enrollment under different authority', function (): void {
    $account = (new User)->forceFill([
        'name' => 'Alice Root',
        'email' => 'alice-root-conflict@example.test',
        'mobile' => '+639173011987',
        'mobile_verified_at' => now(),
        'password' => bcrypt('unused-password'),
    ]);
    $account->save();
    $account->wallet()->firstOrCreate(
        ['slug' => 'platform'],
        ['name' => 'Platform Wallet'],
    );

    $enroll = app(EnrollAffiliationRootAccount::class);
    $enroll->handle($account, 'commissioning:root:alice:v1');

    expect(fn () => $enroll->handle($account, 'commissioning:root:alice:v2'))
        ->toThrow(AffiliationConflict::class);
});

it('keeps affiliation disabled as a non-mutating compatibility mode', function (): void {
    config()->set('x-change.affiliation.enabled', false);

    expect(app(AffiliationInstallationNetwork::class)->ensure())->toBeNull()
        ->and(AffiliationNetwork::query()->count())->toBe(0);
});
