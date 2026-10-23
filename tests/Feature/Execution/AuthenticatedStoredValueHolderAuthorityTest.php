<?php

declare(strict_types=1);

use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Exceptions\StoredValueSpendRejectedException;
use LBHurtado\XChange\Contracts\TreasuryPrincipalReferenceResolverContract;
use LBHurtado\XChange\Services\Execution\AuthenticatedStoredValueHolderAuthority;
use LBHurtado\XChange\Support\Claim\VerifiedStoredValueClaim;

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

it('binds a public reusable balance to the OTP verified contact without a user', function (): void {
    auth()->logout();
    $contact = Contact::fromPhoneNumber(phone('09173011987', 'PH'));
    $claim = new VerifiedStoredValueClaim;
    $claim->establish(['flow_id' => 'flow-123'], [
        'mobile' => '+639173011987',
        'inputs' => ['otp' => [
            'verified' => true,
            'mobile' => '09173011987',
            'reference_id' => 'flow-123',
            'verification_reference' => 'challenge-123',
            'verification_purpose' => 'form_flow.otp',
            'verified_at' => now()->toIso8601String(),
        ]],
    ]);
    $principals = Mockery::mock(TreasuryPrincipalReferenceResolverContract::class);
    $principals->shouldReceive('resolve')->once()->with($contact)->andReturn('principal:contact:verified');

    $authority = new AuthenticatedStoredValueHolderAuthority($principals, $claim);
    $result = $authority->authorize(new ExecutionContextData(
        contact: $contact,
        voucherCode: 'SAFE',
    ));

    expect($result->holder->is($contact))->toBeTrue()
        ->and($result->authorityReference)->toStartWith('stored-value-holder:verified-contact:');
});

it('does not fall back to the signed-in issuer when public OTP proof is missing or mismatched', function (): void {
    actingAsTestUser();
    $contact = Contact::fromPhoneNumber(phone('09173011987', 'PH'));
    $claim = new VerifiedStoredValueClaim;
    $claim->establish(['flow_id' => 'flow-123'], [
        'mobile' => '+639173011987',
        'inputs' => ['otp' => [
            'verified' => true,
            'mobile' => '09170000000',
            'reference_id' => 'flow-123',
            'verification_reference' => 'challenge-123',
            'verification_purpose' => 'form_flow.otp',
            'verified_at' => now()->toIso8601String(),
        ]],
    ]);
    $authority = new AuthenticatedStoredValueHolderAuthority(
        Mockery::mock(TreasuryPrincipalReferenceResolverContract::class),
        $claim,
    );

    expect(fn () => $authority->authorize(new ExecutionContextData(
        contact: $contact,
        voucherCode: 'SAFE',
    )))->toThrow(StoredValueSpendRejectedException::class, 'Verify your mobile number');
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
