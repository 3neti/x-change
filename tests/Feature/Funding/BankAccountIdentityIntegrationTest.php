<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use LBHurtado\XChange\Actions\Banking\AssignNetbankAccount;
use LBHurtado\XChange\Actions\Funding\UpdateFundingDestination;
use LBHurtado\XChange\Models\BankAccountIdentity;
use LBHurtado\XChange\Models\ProviderAccountLink;
use LBHurtado\XChange\Services\Funding\FundingProviderAdapterRegistry;
use LBHurtado\XChange\Tests\Fakes\FakeFundingProviderAdapter;

beforeEach(function (): void {
    $this->app->instance(FundingProviderAdapterRegistry::class, new FundingProviderAdapterRegistry([
        new FakeFundingProviderAdapter('netbank'),
    ]));
});

it('prevents an older link writer from assigning an account claimed by another user', function () {
    $first = actingAsTestUser();
    $second = actingAsTestUser();
    app(AssignNetbankAccount::class)->handle($first, [
        'account_number' => '991100001234', 'account_name' => 'Example',
        'customer_id' => '1234', 'vca_alias' => '54321',
    ]);

    expect(fn () => app(UpdateFundingDestination::class)->handle($second, 'netbank', [
        'mode' => 'dedicated', 'account_number' => '991100001234',
        'account_name' => 'Example', 'vca_alias' => '54321',
    ]))->toThrow(ValidationException::class);
    expect(ProviderAccountLink::query()->count())->toBe(1)
        ->and(ProviderAccountLink::query()->first()->owner_id)->toBe($first->getKey());
});

it('backfills a legacy link before assigning a different account', function () {
    $first = actingAsTestUser();
    $second = actingAsTestUser();
    ProviderAccountLink::query()->create([
        'owner_type' => $first->getMorphClass(), 'owner_id' => $first->getKey(),
        'provider' => 'netbank', 'topology' => 'dedicated',
        'routing_profile_ciphertext' => ['bank_account_number' => '991100001234'],
    ]);
    app(AssignNetbankAccount::class)->handle($second, [
        'account_number' => '991100009999', 'account_name' => 'Example',
        'customer_id' => '1234', 'vca_alias' => '54321',
    ]);

    expect(BankAccountIdentity::query()->count())->toBe(2);
});
