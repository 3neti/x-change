<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Enums\VoucherType;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionOperationContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionReadModelContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionInternalPayableSettlementData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionReservationData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\Wallet\Treasury\Exceptions\TreasuryInvariantViolation;
use LBHurtado\Wallet\Treasury\Models\TreasuryPosition;
use LBHurtado\XChange\Contracts\VerifiedTreasuryFundingAllocationContract;
use LBHurtado\XChange\Data\Disbursement\PayoutDestinationValidationData;
use LBHurtado\XChange\Services\Execution\ValueFlowAddressResolver;
use LBHurtado\XChange\Tests\Fakes\User;

function valueFlowScenarioOwner(string $mobile, int $openingBalanceMinor): User
{
    $owner = User::query()->create([
        'name' => 'Scenario account owner',
        'email' => 'value-flow-'.Str::uuid().'@example.test',
        'password' => 'not-a-login-credential',
    ]);
    $owner->forceFill(['mobile' => $mobile])->save();
    fundTestUserWallet($owner, 0);
    fundTestUserClientFunds($owner, $openingBalanceMinor);

    return $owner;
}

function valueFlowScenarioContext(
    VoucherType $type,
    string $source,
    string $destination,
    int $amountMinor,
    ?string $collectionWalletId = null,
): ExecutionContextData {
    $instructions = validVoucherInstructions(
        amount: $amountMinor / 100,
        overrides: [
            'voucher_type' => $type->value,
            'target_amount' => $type === VoucherType::REDEEMABLE ? null : $amountMinor / 100,
            'metadata' => $collectionWalletId === null ? [] : [
                'collection_wallet_id' => $collectionWalletId,
            ],
            'execution' => [
                'driver' => 'default',
                'metadata' => [
                    'value_flow' => [
                        'source' => $source,
                        'destination' => $destination,
                    ],
                ],
            ],
        ],
    );
    $voucher = Voucher::query()->create([
        'code' => 'VF'.Str::upper(Str::random(8)),
        'metadata' => ['instructions' => $instructions->toArray()],
        'state' => 'active',
    ]);

    return ExecutionContextData::fromRedemption(
        $voucher->refresh(),
        new Contact,
        $voucher->code,
    );
}

it('carries a disbursable instruction into execution context and reserves only its available source funds', function () {
    enableNetbankTreasuryForTests();
    $owner = valueFlowScenarioOwner('09171234567', 10_000);
    $context = valueFlowScenarioContext(
        VoucherType::REDEEMABLE,
        'account.mobile:09171234567.client_funds',
        'instapay.gcash.mobile:09179999999',
        2_500,
    );
    $flow = data_get($context->instruction?->metadata, 'value_flow');
    $amountMinor = (int) round($context->voucher->instructions->cash->amount * 100);
    $currency = $context->voucher->instructions->cash->currency;
    $resolver = app(ValueFlowAddressResolver::class);
    $source = $resolver->resolve($flow['source'], 'netbank-primary', $currency);
    $destination = $resolver->resolve($flow['destination'], 'netbank-primary', $currency);
    $reserve = TreasuryPosition::query()
        ->whereMorphedTo('principal', $owner)
        ->where('connection_reference', 'netbank-primary')
        ->where('purpose', TreasuryPositionPurpose::PayCodeReserve)
        ->sole();

    expect($source)->toBeInstanceOf(TreasuryPositionData::class)
        ->and($destination)->toBeInstanceOf(PayoutDestinationValidationData::class)
        ->and($destination->bankCode)->toBe('GXCHPHM2XXX')
        ->and($destination->accountNumber)->toBe('09179999999');

    $reference = 'value-flow-disbursement:'.$context->voucher->getKey();
    app(TreasuryPositionOperationContract::class)->reserve(new TreasuryPositionReservationData(
        operationReference: $reference,
        sourcePositionReference: $source->positionReference,
        destinationPositionReference: $reserve->position_reference,
        amountMinor: $amountMinor,
        currency: $currency,
        idempotencyKey: $reference,
        externalReference: 'pay-code:'.$context->voucher->getKey(),
    ));

    expect(app(TreasuryPositionReadModelContract::class)->find($source->positionReference)->balanceMinor)
        ->toBe(7_500)
        ->and(app(TreasuryPositionReadModelContract::class)->find($reserve->position_reference)->balanceMinor)
        ->toBe(2_500);
});

it('can allocate trusted payable funding to the named account without crediting the issuer', function () {
    enableNetbankTreasuryForTests();
    $issuer = valueFlowScenarioOwner('09171234567', 5_000);
    $collector = valueFlowScenarioOwner('09173011987', 100);
    $context = valueFlowScenarioContext(
        VoucherType::PAYABLE,
        'instapay.gcash.mobile:09179999999',
        'account.mobile:09173011987.client_funds',
        3_500,
        (string) $collector->wallet->getKey(),
    );
    $flow = data_get($context->instruction?->metadata, 'value_flow');
    $amountMinor = (int) round($context->voucher->instructions->cash->amount * 100);
    $currency = $context->voucher->instructions->cash->currency;
    $resolver = app(ValueFlowAddressResolver::class);
    $source = $resolver->resolve($flow['source'], 'netbank-primary', $currency);
    $destination = $resolver->resolve($flow['destination'], 'netbank-primary', $currency);

    expect($source)->toBeInstanceOf(PayoutDestinationValidationData::class)
        ->and($destination)->toBeInstanceOf(TreasuryPositionData::class)
        ->and($destination->balanceMinor)->toBe(100);

    $evidenceReference = 'test-verified-payment:'.$context->voucher->getKey();
    $allocation = app(VerifiedTreasuryFundingAllocationContract::class)->allocate(
        accountReference: 'wallet:'.$collector->wallet->uuid,
        provider: 'netbank',
        amountMinor: $amountMinor,
        currency: $currency,
        evidenceReference: $evidenceReference,
    );
    $replay = app(VerifiedTreasuryFundingAllocationContract::class)->allocate(
        accountReference: 'wallet:'.$collector->wallet->uuid,
        provider: 'netbank',
        amountMinor: $amountMinor,
        currency: $currency,
        evidenceReference: $evidenceReference,
    );

    expect($allocation->destinationPositionReference)->toBe($destination->positionReference)
        ->and($replay->allocationOperationReference)->toBe($allocation->allocationOperationReference)
        ->and(app(TreasuryPositionReadModelContract::class)->find($destination->positionReference)->balanceMinor)
        ->toBe(3_600)
        ->and(treasuryClientFundsLedger($issuer)->balanceInt)->toBe(5_000);
});

it('shows that account-to-account settlement needs a distinct Treasury operation', function () {
    enableNetbankTreasuryForTests();
    $sender = valueFlowScenarioOwner('09171234567', 10_000);
    $receiver = valueFlowScenarioOwner('09173011987', 100);
    $context = valueFlowScenarioContext(
        VoucherType::SETTLEMENT,
        'account.mobile:09171234567.client_funds',
        'account.mobile:09173011987.client_funds',
        2_500,
        (string) $receiver->wallet->getKey(),
    );
    $flow = data_get($context->instruction?->metadata, 'value_flow');
    $amountMinor = (int) round($context->voucher->instructions->cash->amount * 100);
    $currency = $context->voucher->instructions->cash->currency;
    $resolver = app(ValueFlowAddressResolver::class);
    $source = $resolver->resolve($flow['source'], 'netbank-primary', $currency);
    $destination = $resolver->resolve($flow['destination'], 'netbank-primary', $currency);
    $reference = 'value-flow-settlement:'.$context->voucher->getKey();

    expect($source->positionReference)->not->toBe($destination->positionReference);
    expect(fn () => app(TreasuryPositionOperationContract::class)->settlePayableInternally(new TreasuryPositionInternalPayableSettlementData(
        operationReference: $reference,
        sourcePositionReference: $source->positionReference,
        destinationPositionReference: $destination->positionReference,
        amountMinor: $amountMinor,
        currency: $currency,
        idempotencyKey: $reference,
        externalReference: 'pay-code:'.$context->voucher->getKey(),
    )))->toThrow(TreasuryInvariantViolation::class);

    expect(treasuryClientFundsLedger($sender)->balanceInt)->toBe(10_000)
        ->and(treasuryClientFundsLedger($receiver)->balanceInt)->toBe(100)
        ->and(app(TreasuryPositionReadModelContract::class)->operationExists($reference))->toBeFalse();
});
