<?php

declare(strict_types=1);

use LBHurtado\XChange\Enums\TreasuryOperatorCapability;
use LBHurtado\XChange\Models\TreasuryOperatorAuthorization;
use LBHurtado\XChange\Tests\Fakes\User;

it('grants Treasury maker and checker authority only to separate named humans', function (): void {
    enableNetbankTreasuryForTests();
    $maker = User::query()->create([
        'name' => 'Treasury Maker',
        'email' => 'treasury-maker@example.test',
        'password' => 'password',
    ]);
    $maker->setMobileChannel('09171110001');
    $maker->save();
    $checker = User::query()->create([
        'name' => 'Treasury Checker',
        'email' => 'treasury-checker@example.test',
        'password' => 'password',
    ]);
    $checker->setMobileChannel('09171110002');
    $checker->save();

    $this->artisan('x-change:treasury:authorize-operator', [
        'operator' => $maker->email,
        '--column' => 'email',
        '--capability' => [
            TreasuryOperatorCapability::ViewAccountGrants->value,
            TreasuryOperatorCapability::RequestAccountGrants->value,
        ],
        '--authorization-reference' => 'deployment-control:treasury-maker',
    ])->assertSuccessful();

    $this->artisan('x-change:treasury:authorize-operator', [
        'operator' => $checker->email,
        '--column' => 'email',
        '--capability' => [
            TreasuryOperatorCapability::ViewAccountGrants->value,
            TreasuryOperatorCapability::ApproveAccountGrants->value,
            TreasuryOperatorCapability::ExecuteAccountGrants->value,
        ],
        '--authorization-reference' => 'deployment-control:treasury-checker',
    ])->assertSuccessful();

    expect(TreasuryOperatorAuthorization::query()->count())->toBe(5);

    $this->artisan('x-change:treasury:authorize-operator', [
        'operator' => $maker->email,
        '--column' => 'email',
        '--capability' => [TreasuryOperatorCapability::ApproveAccountGrants->value],
        '--authorization-reference' => 'deployment-control:invalid-combined-role',
    ])->assertFailed();

    expect(TreasuryOperatorAuthorization::query()
        ->where('authorization_reference', 'like', 'deployment-control:invalid-combined-role%')
        ->exists())->toBeFalse();
});
