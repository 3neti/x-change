<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\BankingAccountSelectorContract;
use LBHurtado\XChange\Exceptions\FundingDestinationUnavailable;
use LBHurtado\XChange\Models\FundingDestinationPreference;
use LBHurtado\XChange\Models\ProviderAccountLink;

it('retains shared account selection for existing users', function () {
    $owner = actingAsTestUser();
    $account = app(BankingAccountSelectorContract::class)->select($owner, 'netbank', 'funding');

    expect($account->provider)->toBe('netbank')
        ->and($account->mode)->toBe('shared')
        ->and($account->accountId)->toBeNull();
});

it('selects the dedicated default only for an enabled capability', function () {
    $owner = actingAsTestUser();
    $link = ProviderAccountLink::query()->create([
        'owner_type' => $owner::class,
        'owner_id' => $owner->getKey(),
        'provider' => 'netbank',
        'topology' => 'dedicated',
        'status' => 'ready',
        'capabilities' => ['funding'],
        'ready_at' => now(),
    ]);
    FundingDestinationPreference::query()->create([
        'owner_type' => $owner::class,
        'owner_id' => $owner->getKey(),
        'provider_code' => 'netbank',
        'mode' => 'dedicated',
        'provider_account_link_id' => $link->getKey(),
    ]);

    $selector = app(BankingAccountSelectorContract::class);
    expect($selector->select($owner, 'netbank', 'funding')->accountId)->toBe($link->getKey());
    expect(fn () => $selector->select($owner, 'netbank', 'disbursement'))
        ->toThrow(FundingDestinationUnavailable::class);
});

it('does not let an explicit account select another user’s account', function () {
    $owner = actingAsTestUser();
    $other = actingAsTestUser();
    $link = ProviderAccountLink::query()->create([
        'owner_type' => $other::class,
        'owner_id' => $other->getKey(),
        'provider' => 'netbank',
        'topology' => 'dedicated',
        'status' => 'ready',
        'capabilities' => ['funding'],
        'ready_at' => now(),
    ]);

    expect(fn () => app(BankingAccountSelectorContract::class)->select($owner, 'netbank', 'funding', $link->getKey()))
        ->toThrow(FundingDestinationUnavailable::class);
});

it('selects an explicitly requested account instead of the shared default', function () {
    $owner = actingAsTestUser();
    $link = ProviderAccountLink::query()->create([
        'owner_type' => $owner::class,
        'owner_id' => $owner->getKey(),
        'provider' => 'netbank',
        'topology' => 'dedicated',
        'status' => 'ready',
        'capabilities' => ['funding'],
        'ready_at' => now(),
    ]);

    $account = app(BankingAccountSelectorContract::class)->select($owner, 'netbank', 'funding', $link->getKey());

    expect($account->mode)->toBe('dedicated')
        ->and($account->accountId)->toBe($link->getKey());
});

it('never falls back to the shared account when an assigned account is disabled', function () {
    $owner = actingAsTestUser();
    $link = ProviderAccountLink::query()->create([
        'owner_type' => $owner::class,
        'owner_id' => $owner->getKey(),
        'provider' => 'netbank',
        'topology' => 'dedicated',
        'status' => 'ready',
        'capabilities' => ['funding'],
        'ready_at' => now(),
        'disabled_at' => now(),
    ]);

    expect(fn () => app(BankingAccountSelectorContract::class)->select($owner, 'netbank', 'funding', $link->getKey()))
        ->toThrow(FundingDestinationUnavailable::class);
});

it('rejects an explicit account belonging to another provider', function () {
    $owner = actingAsTestUser();
    $link = ProviderAccountLink::query()->create([
        'owner_type' => $owner::class,
        'owner_id' => $owner->getKey(),
        'provider' => 'netbank',
        'topology' => 'dedicated',
        'status' => 'ready',
        'capabilities' => ['funding'],
        'ready_at' => now(),
    ]);

    expect(fn () => app(BankingAccountSelectorContract::class)->select($owner, 'paynamics', 'funding', $link->getKey()))
        ->toThrow(FundingDestinationUnavailable::class);
});

it('fails closed when a dedicated default no longer points to an account', function () {
    $owner = actingAsTestUser();
    FundingDestinationPreference::query()->create([
        'owner_type' => $owner::class,
        'owner_id' => $owner->getKey(),
        'provider_code' => 'netbank',
        'mode' => 'dedicated',
        'provider_account_link_id' => null,
    ]);

    expect(fn () => app(BankingAccountSelectorContract::class)->select($owner, 'netbank', 'funding'))
        ->toThrow(FundingDestinationUnavailable::class);
});
