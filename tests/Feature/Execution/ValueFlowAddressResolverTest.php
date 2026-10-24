<?php

declare(strict_types=1);

use Bavix\Wallet\Exceptions\InsufficientFunds;
use Illuminate\Support\Str;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionOperationContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionReadModelContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionReservationData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\Wallet\Treasury\Models\TreasuryPosition;
use LBHurtado\XChange\Services\Execution\ValueFlowAddressResolver;
use LBHurtado\XChange\Tests\Fakes\User;

it('resolves concrete internal and InstaPay addresses without treating format validation as settlement', function () {
    enableNetbankTreasuryForTests();
    $owner = User::query()->create([
        'name' => 'Account owner',
        'email' => 'address-owner-'.Str::uuid().'@example.test',
        'password' => 'not-a-login-credential',
    ]);
    $owner->forceFill(['mobile' => '639171234567'])->save();
    fundTestUserClientFunds($owner, 10_000);

    $resolver = app(ValueFlowAddressResolver::class);
    $source = $resolver->clientFunds('account.mobile:09171234567.client_funds', 'netbank-primary', 'PHP');
    $destination = $resolver->instapayDestination('instapay.gcash.mobile:09179999999');

    expect($source->purpose)->toBe(TreasuryPositionPurpose::ClientFunds)
        ->and($source->balanceMinor)->toBe(10_000)
        ->and($source->connectionReference)->toBe('netbank-primary')
        ->and($destination->bankCode)->toBe('GXCHPHM2XXX')
        ->and($destination->accountNumber)->toBe('09179999999')
        ->and($destination->providerVerified)->toBeFalse();
});

it('cannot reserve more than the resolved account owns', function () {
    enableNetbankTreasuryForTests();
    $owner = User::query()->create([
        'name' => 'Account owner',
        'email' => 'address-owner-'.Str::uuid().'@example.test',
        'password' => 'not-a-login-credential',
    ]);
    $owner->forceFill(['mobile' => '09171234567'])->save();
    fundTestUserClientFunds($owner, 10_000);

    $source = app(ValueFlowAddressResolver::class)
        ->clientFunds('account.mobile:09171234567.client_funds', 'netbank-primary', 'PHP');
    $reserve = TreasuryPosition::query()
        ->whereMorphedTo('principal', $owner)
        ->where('connection_reference', 'netbank-primary')
        ->where('purpose', TreasuryPositionPurpose::PayCodeReserve)
        ->sole();
    $operationReference = 'address-proof:'.Str::uuid();
    $reservation = new TreasuryPositionReservationData(
        operationReference: $operationReference,
        sourcePositionReference: $source->positionReference,
        destinationPositionReference: $reserve->position_reference,
        amountMinor: 10_100,
        currency: 'PHP',
        idempotencyKey: $operationReference,
        externalReference: 'address-proof',
    );

    try {
        app(TreasuryPositionOperationContract::class)->reserve($reservation);
        test()->fail('The ledger accepted a reservation greater than Client Funds.');
    } catch (InsufficientFunds) {
        expect(app(TreasuryPositionReadModelContract::class)->find($source->positionReference)->balanceMinor)
            ->toBe(10_000)
            ->and(app(TreasuryPositionReadModelContract::class)->find($reserve->position_reference)->balanceMinor)
            ->toBe(0)
            ->and(app(TreasuryPositionReadModelContract::class)->operationExists($operationReference))
            ->toBeFalse();
    }

    $affordableReference = 'address-proof:'.Str::uuid();
    app(TreasuryPositionOperationContract::class)->reserve(new TreasuryPositionReservationData(
        operationReference: $affordableReference,
        sourcePositionReference: $source->positionReference,
        destinationPositionReference: $reserve->position_reference,
        amountMinor: 10_000,
        currency: 'PHP',
        idempotencyKey: $affordableReference,
        externalReference: 'address-proof',
    ));

    expect(app(TreasuryPositionReadModelContract::class)->find($source->positionReference)->balanceMinor)
        ->toBe(0)
        ->and(app(TreasuryPositionReadModelContract::class)->find($reserve->position_reference)->balanceMinor)
        ->toBe(10_000)
        ->and(app(TreasuryPositionReadModelContract::class)->operationExists($affordableReference))
        ->toBeTrue();
});

it('rejects malformed and unresolved addresses before any money movement', function () {
    $resolver = app(ValueFlowAddressResolver::class);

    expect(fn () => $resolver->instapayDestination('instapay.gcash.mobile:+639179999999'))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $resolver->clientFunds('account.mobile:09171234567.client_funds', 'netbank-primary', 'PHP'))
        ->toThrow(RuntimeException::class);
});

it('rejects a mobile that belongs to more than one account', function () {
    foreach (['09171234567', '639171234567'] as $mobile) {
        $owner = User::query()->create([
            'name' => 'Duplicate account owner',
            'email' => 'address-owner-'.Str::uuid().'@example.test',
            'password' => 'not-a-login-credential',
        ]);
        $owner->forceFill(['mobile' => $mobile])->save();
    }

    expect(fn () => app(ValueFlowAddressResolver::class)
        ->clientFunds('account.mobile:09171234567.client_funds', 'netbank-primary', 'PHP'))
        ->toThrow(RuntimeException::class, 'exactly one principal');
});
