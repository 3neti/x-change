<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use LBHurtado\XChange\Actions\Banking\AssignNetbankAccount;
use LBHurtado\XChange\Actions\Banking\SelectDefaultBankingAccount;
use LBHurtado\XChange\Contracts\BankingAccountSelectorContract;
use LBHurtado\XChange\Exceptions\FundingDestinationUnavailable;
use LBHurtado\XChange\Exceptions\InsufficientWalletBalance;
use LBHurtado\XChange\Models\FundingDestinationPreference;
use LBHurtado\XChange\Services\ProviderAwareFundingPolicy;

it('selects an assigned account only when funding is ready and keeps disbursement separate', function () {
    $owner = actingAsTestUser();
    $assignment = app(AssignNetbankAccount::class)->handle($owner, [
        'account_number' => '991100001234', 'account_name' => 'Example',
        'customer_id' => '1234', 'vca_alias' => '54321',
    ]);
    $setDefault = app(SelectDefaultBankingAccount::class);

    expect(fn () => $setDefault->handle($owner, $assignment->id))->toThrow(ValidationException::class);
    $assignment->providerAccountLink->forceFill([
        'status' => 'ready', 'ready_at' => now(), 'capabilities' => ['funding'],
    ])->save();
    $setDefault->handle($owner, $assignment->id);
    $selector = app(BankingAccountSelectorContract::class);

    expect($selector->select($owner, 'netbank', 'funding')->accountId)
        ->toBe($assignment->provider_account_link_id)
        ->and(fn () => $selector->select($owner, 'netbank', 'disbursement'))
        ->toThrow(FundingDestinationUnavailable::class);
});

it('does not select another user’s assigned account', function () {
    $owner = actingAsTestUser();
    $other = actingAsTestUser();
    $assignment = app(AssignNetbankAccount::class)->handle($owner, [
        'account_number' => '991100001234', 'account_name' => 'Example',
        'customer_id' => '1234', 'vca_alias' => '54321',
    ]);

    expect(fn () => app(SelectDefaultBankingAccount::class)->handle($other, $assignment->id))
        ->toThrow(ValidationException::class);
});

it('blocks issuance from an assigned account until account-specific payout routing exists', function () {
    $owner = actingAsTestUser();
    $assignment = app(AssignNetbankAccount::class)->handle($owner, [
        'account_number' => '991100001234', 'account_name' => 'Example',
        'customer_id' => '1234', 'vca_alias' => '54321',
    ]);
    $assignment->providerAccountLink->forceFill([
        'status' => 'ready', 'ready_at' => now(), 'capabilities' => ['funding'],
    ])->save();
    app(SelectDefaultBankingAccount::class)->handle($owner, $assignment->id);

    expect(fn () => app(ProviderAwareFundingPolicy::class)->assertCanIssue($owner, $owner->wallet, 25, ['provider' => 'netbank']))
        ->toThrow(InsufficientWalletBalance::class, 'selected bank account is not available');
});

it('does not bypass the assigned account issuance guard in an isolated lifecycle', function () {
    $owner = actingAsTestUser(100_000);
    $assignment = app(AssignNetbankAccount::class)->handle($owner, [
        'account_number' => '991100001234', 'account_name' => 'Example',
        'customer_id' => '1234', 'vca_alias' => '54321',
    ]);
    $assignment->providerAccountLink->forceFill([
        'status' => 'ready', 'ready_at' => now(), 'capabilities' => ['funding'],
    ])->save();
    app(SelectDefaultBankingAccount::class)->handle($owner, $assignment->id);

    expect(fn () => app(ProviderAwareFundingPolicy::class)->assertCanIssue($owner, $owner->wallet, 25, [
        'provider' => 'netbank', 'lifecycle_funding_boundary' => 'isolated_compatibility_wallet',
    ]))->toThrow(InsufficientWalletBalance::class, 'selected bank account is not available');
});

it('does not fall back to shared funds when the selected bank account is unavailable', function () {
    $owner = actingAsTestUser(100_000);
    $other = actingAsTestUser();
    $assignment = app(AssignNetbankAccount::class)->handle($other, [
        'account_number' => '991100001234', 'account_name' => 'Other', 'customer_id' => '1234', 'vca_alias' => '54321',
    ]);
    FundingDestinationPreference::query()->create([
        'owner_type' => $owner->getMorphClass(), 'owner_id' => $owner->getKey(), 'provider_code' => 'netbank',
        'mode' => 'dedicated', 'provider_account_link_id' => $assignment->provider_account_link_id,
    ]);
    expect(fn () => app(ProviderAwareFundingPolicy::class)->assertCanIssue($owner, $owner->wallet, 25.0, ['provider' => 'netbank']))
        ->toThrow(InsufficientWalletBalance::class, 'selected bank account is unavailable');
});
