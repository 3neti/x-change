<?php

declare(strict_types=1);

use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Exceptions\StoredValueSpendRejectedException;
use LBHurtado\XChange\Contracts\TreasuryPrincipalReferenceResolverContract;
use LBHurtado\XChange\Services\Execution\AuthenticatedStoredValueHolderAuthority;

it('binds activation only to the authenticated verified mobile Account', function (): void {
    $holder = actingAsTestUser();
    $holder->forceFill([
        'mobile' => '09173011987',
        'mobile_verified_at' => now(),
    ])->save();
    $principals = Mockery::mock(TreasuryPrincipalReferenceResolverContract::class);
    $principals->shouldReceive('resolve')->once()->with($holder)->andReturn('principal:holder:verified');
    $authority = new AuthenticatedStoredValueHolderAuthority($principals);

    $result = $authority->authorize(new ExecutionContextData(
        contact: new Contact(['mobile' => '+639173011987']),
        voucherCode: 'SAFE',
    ));

    expect($result->holder->is($holder))->toBeTrue()
        ->and($result->principalReference)->toBe('principal:holder:verified');
});

it('rejects an unverified or mismatched holder', function (): void {
    $holder = actingAsTestUser();
    $holder->forceFill(['mobile' => '09173011987'])->save();
    $authority = new AuthenticatedStoredValueHolderAuthority(
        Mockery::mock(TreasuryPrincipalReferenceResolverContract::class),
    );

    expect(fn () => $authority->authorize(new ExecutionContextData(
        contact: new Contact(['mobile' => '09173011987']),
        voucherCode: 'SAFE',
    )))->toThrow(StoredValueSpendRejectedException::class, 'verified mobile number');

    $holder->forceFill(['mobile_verified_at' => now()])->save();

    expect(fn () => $authority->authorize(new ExecutionContextData(
        contact: new Contact(['mobile' => '09170000000']),
        voucherCode: 'SAFE',
    )))->toThrow(StoredValueSpendRejectedException::class, 'verified mobile number');
});
