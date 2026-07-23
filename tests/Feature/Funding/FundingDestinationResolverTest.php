<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Actions\Funding\CreateFundingIntent;
use LBHurtado\XChange\Contracts\FundingDestinationResolverContract;
use LBHurtado\XChange\Data\Funding\CreateFundingIntentData;
use LBHurtado\XChange\Exceptions\FundingDestinationUnavailable;
use LBHurtado\XChange\Models\FundingDestinationPreference;
use LBHurtado\XChange\Models\ProviderAccountLink;

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
        ->and($destination->routingCredential)->toBe('test-vca-alias-token');
});

it('keeps the one-time NetBank destination unavailable without its alias token', function () {
    $owner = actingAsTestUser();
    config()->set('payment-gateway.netbank.funding.vca_alias_token');

    expect(fn () => app(FundingDestinationResolverContract::class)->resolve(
        $owner,
        'netbank',
        'wallet:shared',
    ))->toThrow(FundingDestinationUnavailable::class, 'vca_alias_token');
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
        'verification_status' => 'verified',
        'display_reference' => '•••• 1234 · VCA 54321',
        'routing_fingerprint' => hash('sha256', 'netbank|991100001234|54321'),
        'routing_profile_ciphertext' => [
            'bank_account_number' => '991100001234',
            'bank_account_name' => 'Dedicated Treasury',
            'vca_alias' => '54321',
            'vca_alias_token' => 'write-only-token',
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
        ->and($destination->routingCredential)->toBe('write-only-token')
        ->and($raw->routing_profile_ciphertext)->not->toContain('991100001234')
        ->and($raw->routing_profile_ciphertext)->not->toContain('write-only-token');
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
        ->and($intent->destination_fingerprint)->toBe($destination->fingerprint)
        ->and($raw->destination_snapshot_ciphertext)->not->toContain('113001000019')
        ->and($raw->destination_snapshot_ciphertext)->not->toContain('test-vca-alias-token');
});
