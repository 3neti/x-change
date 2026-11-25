<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use LBHurtado\XChange\Actions\Banking\AssignNetbankAccount;
use LBHurtado\XChange\Actions\Banking\UpdateNetbankAccountFormat;

it('records the provider format without changing the account identity', function () {
    $owner = actingAsTestUser();
    $assignment = app(AssignNetbankAccount::class)->handle($owner, [
        'account_number' => '991100001234', 'account_name' => 'Example', 'customer_id' => '1234', 'vca_alias' => '54321',
    ]);

    app(UpdateNetbankAccountFormat::class)->handle($assignment, '991-100-00123-4', $owner);
    app(UpdateNetbankAccountFormat::class)->handle($assignment, '991-100-00123-4', $owner);

    expect($assignment->fresh()->details_ciphertext)->toMatchArray([
        'account_number' => '991100001234', 'provider_account_number' => '991-100-00123-4',
    ])->and($this->fakeAuditLogger()->events('bank.account.provider_format_updated'))->toHaveCount(1);
});

it('rejects a provider format that changes the account identity', function () {
    $owner = actingAsTestUser();
    $assignment = app(AssignNetbankAccount::class)->handle($owner, [
        'account_number' => '991100001234', 'account_name' => 'Example', 'customer_id' => '1234', 'vca_alias' => '54321',
    ]);

    expect(fn () => app(UpdateNetbankAccountFormat::class)->handle($assignment, '991-100-00999-9', $owner))
        ->toThrow(ValidationException::class);
    expect($assignment->fresh()->details_ciphertext['provider_account_number'])->toBe('991100001234');
});
