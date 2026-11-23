<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LBHurtado\XChange\Actions\Banking\AssignNetbankAccount;
use LBHurtado\XChange\Actions\Banking\ConfigureAssignedBankAccount;
use LBHurtado\XChange\Actions\Banking\SelectDefaultBankingAccount;
use LBHurtado\XChange\Services\Banking\AssignedBankTreasuryConnection;
use LBHurtado\XChange\Services\CheckProviderSourceAccountReadiness;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;

function configuredAssignedAccountFixture(): array
{
    $owner = actingAsTestUser();
    $assignment = app(AssignNetbankAccount::class)->handle($owner, [
        'account_number' => '991100001234', 'account_name' => 'Example', 'customer_id' => '1234', 'vca_alias' => '54321',
    ]);
    $catalog = new TreasuryProviderConnectionCatalog(['personal' => [
        'provider' => 'netbank', 'mode' => 'required', 'currency' => 'PHP',
        'inventory_reference' => 'inventory:personal', 'settlement_resource_reference' => 'resource:personal',
        'settlement_resource_type' => 'cash_at_bank', 'custody_mode' => 'provider_projection',
    ]]);

    app()->instance(TreasuryProviderConnectionCatalog::class, $catalog);

    return [$owner, $assignment, new AssignedBankTreasuryConnection($catalog)];
}

it('binds an administered account after checking its selected bank balance', function () {
    [$owner, $assignment, $binding] = configuredAssignedAccountFixture();
    $readiness = Mockery::mock(CheckProviderSourceAccountReadiness::class);
    $readiness->shouldReceive('handle')->once()->with(0, 'netbank', '991100001234')
        ->andReturn(['ready' => true, 'checked' => true, 'currency' => 'PHP', 'available_balance_minor' => 0]);
    (new ConfigureAssignedBankAccount($binding, $readiness))->handle($assignment, 'personal', $owner);
    $link = $assignment->fresh()->providerAccountLink;
    expect($assignment->fresh()->treasury_connection_reference)->toBe('personal')
        ->and($link->isReady())->toBeTrue()
        ->and($link->capabilities)->toContain('disbursement')->not->toContain('funding')
        ->and($link->verification_status)->toBe('routing_configured');
    $preference = app(SelectDefaultBankingAccount::class)->handle($owner, $assignment->id);
    expect($preference->provider_account_link_id)->toBe($link->id);
});

it('does not bind or activate an account when the bank balance check fails', function () {
    [$owner, $assignment, $binding] = configuredAssignedAccountFixture();
    $readiness = Mockery::mock(CheckProviderSourceAccountReadiness::class);
    $readiness->shouldReceive('handle')->once()->andReturn(['ready' => false, 'checked' => true]);
    expect(fn () => (new ConfigureAssignedBankAccount($binding, $readiness))->handle($assignment, 'personal', $owner))
        ->toThrow(ValidationException::class);
    expect($assignment->fresh()->treasury_connection_reference)->toBeNull()
        ->and($assignment->fresh()->providerAccountLink->isReady())->toBeFalse();
});

it('enables funding separately after historical references are reviewed', function () {
    [$owner, $assignment, $binding] = configuredAssignedAccountFixture();
    $binding->bind($owner, $assignment->id, 'personal');
    config()->set('x-change.funding.dedicated_netbank_vca_enabled', true);
    $readiness = Mockery::mock(CheckProviderSourceAccountReadiness::class);
    $readiness->shouldNotReceive('handle');

    $action = new ConfigureAssignedBankAccount($binding, $readiness);
    $action->enableFunding($assignment, $owner, true);
    $action->enableFunding($assignment, $owner, true);

    expect($this->fakeAuditLogger()->events('funding.assigned_account.enabled'))->toHaveCount(1);
    $link = $assignment->fresh()->providerAccountLink;
    expect($link->isReady())->toBeTrue()
        ->and($link->capabilities)->toContain('funding')->not->toContain('disbursement')
        ->and(data_get($link->metadata, 'funding_assignment.actor_id'))->toBe((string) $owner->getKey());
});

it('does not enable funding without reviewed history or when dedicated funding is disabled', function () {
    [$owner, $assignment, $binding] = configuredAssignedAccountFixture();
    $binding->bind($owner, $assignment->id, 'personal');
    $readiness = Mockery::mock(CheckProviderSourceAccountReadiness::class);
    $action = new ConfigureAssignedBankAccount($binding, $readiness);
    config()->set('x-change.funding.dedicated_netbank_vca_enabled', true);
    expect(fn () => $action->enableFunding($assignment, $owner, false))->toThrow(ValidationException::class);
    config()->set('x-change.funding.dedicated_netbank_vca_enabled', false);
    expect(fn () => $action->enableFunding($assignment, $owner, true))->toThrow(ValidationException::class);
    expect($assignment->fresh()->providerAccountLink->capabilities)->not->toContain('funding');
});

it('refuses funding setup when the account routing does not match', function () {
    [$owner, $assignment, $binding] = configuredAssignedAccountFixture();
    $binding->bind($owner, $assignment->id, 'personal');
    config()->set('x-change.funding.dedicated_netbank_vca_enabled', true);
    $link = $assignment->providerAccountLink;
    $routing = $link->routing_profile_ciphertext;
    $routing['vca_alias'] = '54322';
    $link->forceFill(['routing_profile_ciphertext' => $routing])->save();
    $readiness = Mockery::mock(CheckProviderSourceAccountReadiness::class);

    expect(fn () => (new ConfigureAssignedBankAccount($binding, $readiness))->enableFunding($assignment, $owner, true))
        ->toThrow(ValidationException::class);
    expect($link->fresh()->capabilities)->not->toContain('funding');
});

it('requires a dedicated treasury binding before enabling funding', function () {
    [$owner, $assignment, $binding] = configuredAssignedAccountFixture();
    config()->set('x-change.funding.dedicated_netbank_vca_enabled', true);
    $readiness = Mockery::mock(CheckProviderSourceAccountReadiness::class);

    expect(fn () => (new ConfigureAssignedBankAccount($binding, $readiness))->enableFunding($assignment, $owner, true))
        ->toThrow(ValidationException::class, 'separate Treasury connection');
    expect($assignment->fresh()->providerAccountLink->capabilities)->not->toContain('funding');
});

it('checks the bank balance using the account number in its provider format', function () {
    $owner = actingAsTestUser();
    $assignment = app(AssignNetbankAccount::class)->handle($owner, [
        'account_number' => '991-100-00123-4', 'account_name' => 'Example', 'customer_id' => '1234', 'vca_alias' => '54321',
    ]);
    $catalog = new TreasuryProviderConnectionCatalog(['personal' => [
        'provider' => 'netbank', 'mode' => 'required', 'currency' => 'PHP',
        'inventory_reference' => 'inventory:personal', 'settlement_resource_reference' => 'resource:personal',
        'settlement_resource_type' => 'cash_at_bank', 'custody_mode' => 'provider_projection',
    ]]);
    $readiness = Mockery::mock(CheckProviderSourceAccountReadiness::class);
    $readiness->shouldReceive('handle')->once()->with(0, 'netbank', '991-100-00123-4')
        ->andReturn(['ready' => true, 'checked' => true, 'currency' => 'PHP', 'available_balance_minor' => 0]);

    (new ConfigureAssignedBankAccount(new AssignedBankTreasuryConnection($catalog), $readiness))->handle($assignment, 'personal', $owner);

    expect($assignment->fresh()->providerAccountLink->capabilities)->toContain('disbursement');
});

it('refuses a provider format that identifies a different account before contacting the bank', function () {
    [$owner, $assignment, $binding] = configuredAssignedAccountFixture();
    $details = $assignment->details_ciphertext;
    $details['provider_account_number'] = '991-100-00999-9';
    $assignment->forceFill(['details_ciphertext' => $details])->save();
    $readiness = Mockery::mock(CheckProviderSourceAccountReadiness::class);
    $readiness->shouldNotReceive('handle');

    expect(fn () => (new ConfigureAssignedBankAccount($binding, $readiness))->handle($assignment, 'personal', $owner))
        ->toThrow(ValidationException::class, 'assigned account is unavailable');
});

it('commissions only fresh funding for an assigned account without claiming historical settlement was reviewed', function () {
    [$owner, $assignment, $binding] = configuredAssignedAccountFixture();
    $binding->bind($owner, $assignment->id, 'personal');
    config()->set('x-change.funding.dedicated_netbank_vca_enabled', true);
    $readiness = Mockery::mock(CheckProviderSourceAccountReadiness::class);
    $readiness->shouldNotReceive('handle');
    $action = new ConfigureAssignedBankAccount($binding, $readiness);

    $action->enableFreshFunding($assignment, $owner, ['543210000001'], true);
    $action->enableFreshFunding($assignment, $owner, ['543210000001'], true);

    $link = $assignment->fresh()->providerAccountLink;
    expect($link->capabilities)->toContain('funding')
        ->and(data_get($link->metadata, 'funding_assignment.scope'))->toBe('fresh_intents_only')
        ->and(data_get($link->metadata, 'funding_assignment.historical_references_reviewed'))->toBeFalse()
        ->and(data_get($link->metadata, 'funding_assignment.fresh_since'))->toBeString()
        ->and(DB::table('xchange_funding_vca_reservations')->count())->toBe(1)
        ->and($this->fakeAuditLogger()->events('funding.assigned_account.enabled'))->toHaveCount(1);
});

it('does not commission fresh funding without sole-writer confirmation or with a different alias', function () {
    [$owner, $assignment, $binding] = configuredAssignedAccountFixture();
    $binding->bind($owner, $assignment->id, 'personal');
    config()->set('x-change.funding.dedicated_netbank_vca_enabled', true);
    $action = new ConfigureAssignedBankAccount($binding, Mockery::mock(CheckProviderSourceAccountReadiness::class));

    expect(fn () => $action->enableFreshFunding($assignment, $owner, ['543210000001'], false))->toThrow(ValidationException::class);
    expect(fn () => $action->enableFreshFunding($assignment, $owner, ['543220000001'], true))->toThrow(ValidationException::class);
    expect($assignment->fresh()->providerAccountLink->capabilities)->not->toContain('funding')
        ->and(DB::table('xchange_funding_vca_reservations')->count())->toBe(0);
});

it('rolls back historical reservations if the fresh account has no treasury binding', function () {
    [$owner, $assignment, $binding] = configuredAssignedAccountFixture();
    config()->set('x-change.funding.dedicated_netbank_vca_enabled', true);
    $action = new ConfigureAssignedBankAccount($binding, Mockery::mock(CheckProviderSourceAccountReadiness::class));

    expect(fn () => $action->enableFreshFunding($assignment, $owner, ['543210000001'], true))->toThrow(ValidationException::class);
    expect(DB::table('xchange_funding_vca_reservations')->count())->toBe(0)
        ->and($assignment->fresh()->providerAccountLink->capabilities)->not->toContain('funding');
});
