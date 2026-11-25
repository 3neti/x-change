<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Actions\Banking\AssignNetbankAccount;
use LBHurtado\XChange\Actions\Banking\SelectDefaultBankingAccount;
use LBHurtado\XChange\Actions\Funding\CreateFundingIntent;
use LBHurtado\XChange\Contracts\FundingDestinationResolverContract;
use LBHurtado\XChange\Data\Funding\CreateFundingIntentData;
use LBHurtado\XChange\Exceptions\FundingDestinationUnavailable;
use LBHurtado\XChange\Models\FundingDestinationPreference;
use LBHurtado\XChange\Models\ProviderAccountLink;
use LBHurtado\XChange\Services\Banking\AssignedBankTreasuryConnection;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;

it('defaults existing owners to the shared platform destination', function () {
    $owner = actingAsTestUser();

    $destination = app(FundingDestinationResolverContract::class)->resolve(
        $owner,
        'netbank',
        'wallet:shared',
    );

    expect($destination->mode)->toBe('shared')
        ->and($destination->provider)->toBe('netbank')
        ->and($destination->displayReference)->toBe('•••• 0019 · VCA 91500')
        ->and($destination->bankAccountNumber)->toBe('113001000019')
        ->and($destination->routingCredential)->toBeNull();
});

it('keeps one-time NetBank routing available without a persisted alias token', function () {
    $owner = actingAsTestUser();
    config()->set('payment-gateway.netbank.funding.vca_alias_token');

    $destination = app(FundingDestinationResolverContract::class)->resolve(
        $owner,
        'netbank',
        'wallet:shared',
    );

    expect($destination->routingAlias)->toBe('91500')
        ->and($destination->routingCredential)->toBeNull();
});

it('resolves an active dedicated NetBank destination and encrypts its routing profile', function () {
    $owner = actingAsTestUser();
    $link = ProviderAccountLink::query()->create([
        'owner_type' => $owner::class,
        'owner_id' => $owner->getKey(),
        'provider' => 'netbank',
        'topology' => 'dedicated',
        'purpose' => 'funding',
        'mode' => 'dedicated',
        'status' => 'ready',
        'verification_status' => 'routing_configured',
        'display_reference' => '•••• 1234 · VCA 54321',
        'routing_fingerprint' => hash('sha256', 'netbank|991100001234|54321'),
        'routing_profile_ciphertext' => [
            'bank_account_number' => '991100001234',
            'bank_account_name' => 'Dedicated Treasury',
            'vca_alias' => '54321',
        ],
        'ready_at' => now(),
        'verified_at' => now(),
        'activated_at' => now(),
    ]);
    FundingDestinationPreference::query()->create([
        'owner_type' => $owner::class,
        'owner_id' => $owner->getKey(),
        'provider_code' => 'netbank',
        'mode' => 'dedicated',
        'provider_account_link_id' => $link->getKey(),
    ]);

    $destination = app(FundingDestinationResolverContract::class)->resolve(
        $owner,
        'netbank',
        'wallet:dedicated',
    );
    $raw = DB::table('xchange_provider_account_links')->find($link->getKey());

    expect($destination->mode)->toBe('dedicated')
        ->and($destination->bankAccountNumber)->toBe('991100001234')
        ->and($destination->routingAlias)->toBe('54321')
        ->and($destination->routingCredential)->toBeNull()
        ->and($raw->routing_profile_ciphertext)->not->toContain('991100001234')
        ->and($raw->routing_profile_ciphertext)->not->toContain('vca_alias_token');
});

it('fails closed for a dedicated Paynamics wallet without ownership proof', function () {
    $owner = actingAsTestUser();
    $link = ProviderAccountLink::query()->create([
        'owner_type' => $owner::class,
        'owner_id' => $owner->getKey(),
        'provider' => 'paynamics',
        'topology' => 'dedicated',
        'purpose' => 'funding',
        'mode' => 'dedicated',
        'provider_wallet_id' => 'CNSTWLLT-REACHABLE',
        'status' => 'ready',
        'verification_status' => 'reachable',
        'ready_at' => now(),
    ]);
    FundingDestinationPreference::query()->create([
        'owner_type' => $owner::class,
        'owner_id' => $owner->getKey(),
        'provider_code' => 'paynamics_constellation',
        'mode' => 'dedicated',
        'provider_account_link_id' => $link->getKey(),
    ]);

    expect(fn () => app(FundingDestinationResolverContract::class)->resolve(
        $owner,
        'paynamics_constellation',
        'wallet:dedicated',
    ))->toThrow(FundingDestinationUnavailable::class, 'ownership verification');
});

it('encrypts an immutable destination snapshot on a Funding Intent', function () {
    config()->set('x-change.funding.providers.netbank.enabled', true);
    $owner = actingAsTestUser();
    $destination = app(FundingDestinationResolverContract::class)->resolve(
        $owner,
        'netbank',
        'wallet:shared',
    );

    $intent = app(CreateFundingIntent::class)->handle(new CreateFundingIntentData(
        accountReference: 'wallet:shared',
        provider: 'netbank',
        expectedAmountMinor: 25_000,
        currency: 'PHP',
        idempotencyKey: 'destination-snapshot-test',
        actorType: $owner::class,
        actorId: (string) $owner->getKey(),
        destination: $destination,
    ));
    $raw = DB::table('x_change_funding_intents')->find($intent->getKey());

    expect($intent->destination_snapshot_ciphertext['bankAccountNumber'])->toBe('113001000019')
        ->and($intent->destination_snapshot_ciphertext['routingCredential'])->toBeNull()
        ->and($intent->destination_fingerprint)->toBe($destination->fingerprint)
        ->and($raw->destination_snapshot_ciphertext)->not->toContain('113001000019')
        ->and($raw->destination_snapshot_ciphertext)->not->toContain('vca_alias_token');
});

it('freezes the assigned account Treasury binding in funding destination metadata', function () {
    $owner = actingAsTestUser();
    $assignment = app(AssignNetbankAccount::class)->handle($owner, [
        'account_number' => '991-100-00123-4', 'account_name' => 'Example', 'customer_id' => '1234', 'vca_alias' => '54321',
    ]);
    $assignment->providerAccountLink->forceFill([
        'status' => 'ready', 'verification_status' => 'routing_configured', 'ready_at' => now(), 'capabilities' => ['funding'],
        'metadata' => ['funding_assignment' => ['instruction_mode' => 'direct_qr']],
    ])->save();
    app(SelectDefaultBankingAccount::class)->handle($owner, $assignment->id);
    $catalog = new TreasuryProviderConnectionCatalog([
        'personal' => [
            'provider' => 'netbank', 'mode' => 'required', 'currency' => 'PHP',
            'inventory_reference' => 'inventory:personal', 'settlement_resource_reference' => 'resource:personal',
            'settlement_resource_type' => 'cash_at_bank', 'custody_mode' => 'provider_projection',
        ],
    ]);
    app()->instance(TreasuryProviderConnectionCatalog::class, $catalog);
    app(AssignedBankTreasuryConnection::class)->bind($owner, $assignment->id, 'personal');

    $destination = app(FundingDestinationResolverContract::class)->resolve($owner, 'netbank', 'wallet:personal');
    expect($destination->bankAccountNumber)->toBe('991-100-00123-4');
    expect($destination->metadata)->toMatchArray([
        'assigned_bank_account_link_id' => $assignment->provider_account_link_id,
        'netbank_instruction_mode' => 'direct_qr',
        'treasury_connection_reference' => 'personal',
        'treasury_inventory_reference' => 'inventory:personal',
        'treasury_resource_reference' => 'resource:personal',
    ]);
    config()->set('x-change.funding.providers.netbank.enabled', true);
    $intent = app(CreateFundingIntent::class)->handle(new CreateFundingIntentData(
        accountReference: 'wallet:personal', provider: 'netbank', expectedAmountMinor: 2500, currency: 'PHP',
        idempotencyKey: 'account-treasury-snapshot', actorType: $owner::class,
        actorId: (string) $owner->getKey(), destination: $destination,
    ));
    expect(data_get($intent->destination_snapshot_ciphertext, 'metadata.netbank_instruction_mode'))->toBe('direct_qr')
        ->and(data_get($intent->destination_snapshot_ciphertext, 'bankAccountNumber'))->toBe('991-100-00123-4')
        ->and(data_get($intent->destination_snapshot_ciphertext, 'metadata.treasury_connection_reference'))->toBe('personal')
        ->and(DB::table('x_change_funding_intents')->find($intent->getKey())->destination_snapshot_ciphertext)
        ->not->toContain('inventory:personal');
    $details = $assignment->details_ciphertext;
    $invalidDetails = $details;
    $invalidDetails['provider_account_number'] = '991-100-00999-9';
    $assignment->forceFill(['details_ciphertext' => $invalidDetails])->save();
    expect(fn () => app(FundingDestinationResolverContract::class)->resolve($owner, 'netbank', 'wallet:personal'))
        ->toThrow(FundingDestinationUnavailable::class, 'funding details are inconsistent');
    $assignment->forceFill(['details_ciphertext' => $details])->save();
    $link = $assignment->providerAccountLink->fresh();
    $routing = $link->routing_profile_ciphertext;
    $routing['bank_account_number'] = '991100009999';
    $link->forceFill(['routing_profile_ciphertext' => $routing])->save();
    expect(fn () => app(FundingDestinationResolverContract::class)->resolve($owner, 'netbank', 'wallet:personal'))
        ->toThrow(FundingDestinationUnavailable::class, 'funding details are inconsistent');
});

it('rejects an assigned account that is generally ready but lacks funding readiness', function () {
    $owner = actingAsTestUser();
    $assignment = app(AssignNetbankAccount::class)->handle($owner, [
        'account_number' => '991100001234', 'account_name' => 'Example', 'customer_id' => '1234', 'vca_alias' => '54321',
    ]);
    $link = $assignment->providerAccountLink;
    $link->forceFill(['status' => 'ready', 'ready_at' => now(), 'capabilities' => ['disbursement']])->save();
    FundingDestinationPreference::query()->create([
        'owner_type' => $owner::class, 'owner_id' => $owner->getKey(), 'provider_code' => 'netbank',
        'mode' => 'dedicated', 'provider_account_link_id' => $link->getKey(),
    ]);

    expect(fn () => app(FundingDestinationResolverContract::class)->resolve($owner, 'netbank', 'wallet:personal'))
        ->toThrow(FundingDestinationUnavailable::class, 'not ready for funding');
});
