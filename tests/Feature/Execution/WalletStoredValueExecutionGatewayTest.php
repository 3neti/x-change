<?php

declare(strict_types=1);

use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Data\ExecutionInstructionData;
use LBHurtado\Voucher\Exceptions\StoredValueSpendRejectedException;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryAllocationOperationContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryAllocationReadModelContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationOperationData;
use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationReadModelData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryAllocationOperationType;
use LBHurtado\XChange\Actions\Treasury\ReleaseExpiredPayCodeReserve;
use LBHurtado\XChange\Actions\Treasury\ReleasePayCodeTerminalReserve;
use LBHurtado\XChange\Contracts\Execution\StoredValueDestinationAuthorityContract;
use LBHurtado\XChange\Contracts\Execution\StoredValueHolderAuthorityContract;
use LBHurtado\XChange\Contracts\TreasuryPrincipalReferenceResolverContract;
use LBHurtado\XChange\Data\Execution\StoredValueDestinationAuthorityData;
use LBHurtado\XChange\Data\Execution\StoredValueHolderAuthorityData;
use LBHurtado\XChange\Exceptions\TreasuryConfigurationException;
use LBHurtado\XChange\Models\StoredValueHolderBinding;
use LBHurtado\XChange\Models\VoucherClaim;
use LBHurtado\XChange\Services\Execution\UnavailableStoredValueDestinationAuthority;
use LBHurtado\XChange\Services\Execution\WalletStoredValueExecutionGateway;
use LBHurtado\XChange\Services\Treasury\PayCodeTerminalReleaseJournal;
use LBHurtado\XChange\Services\Treasury\TreasuryPayCodeAccountingService;

it('activates one wallet-backed allocation and replays the immutable holder binding', function (): void {
    $holder = actingAsTestUser();
    $voucher = storedValueVoucher();
    $context = storedValueContext($voucher, 'activation-1');
    $operations = Mockery::mock(TreasuryAllocationOperationContract::class);
    $readModel = Mockery::mock(TreasuryAllocationReadModelContract::class);
    $holderAuthority = Mockery::mock(StoredValueHolderAuthorityContract::class);
    $principalReferences = Mockery::mock(TreasuryPrincipalReferenceResolverContract::class);

    $holderAuthority->shouldReceive('authorize')->twice()->andReturn(
        new StoredValueHolderAuthorityData(
            holder: $holder,
            authorityReference: 'holder-authority:commissioned',
            principalReference: 'principal:holder:one',
        ),
    );
    $principalReferences->shouldReceive('resolve')->twice()->with($holder)
        ->andReturn('principal:holder:one');
    $operations->shouldReceive('activate')->once()->withArgs(function ($activation): bool {
        expect($activation->backingReservationOperationReference)->toBe('reservation:stored-value:1')
            ->and($activation->initialAmountMinor)->toBe(100_000)
            ->and($activation->maximumAmountMinor)->toBe(100_000)
            ->and($activation->currency)->toBe('PHP')
            ->and($activation->replenishable)->toBeFalse();

        return true;
    })->andReturn(storedValueOperation(
        type: TreasuryAllocationOperationType::Activation,
        amountMinor: 100_000,
        balanceBeforeMinor: 0,
        balanceAfterMinor: 100_000,
    ));
    $readModel->shouldReceive('read')->twice()->andReturn(storedValueReadModel(100_000));

    $gateway = new WalletStoredValueExecutionGateway(
        allocationOperations: $operations,
        allocationReadModel: $readModel,
        holderAuthority: $holderAuthority,
        destinationAuthority: new UnavailableStoredValueDestinationAuthority,
        principalReferences: $principalReferences,
    );

    $first = $gateway->activate($context, 'activation-1');
    $second = $gateway->activate($context, 'activation-1');
    $binding = StoredValueHolderBinding::query()->sole();

    expect($first['remaining_balance'])->toBe(100_000)
        ->and($second)->toBe($first)
        ->and($binding->voucher_id)->toBe($voucher->getKey())
        ->and($binding->holder_type)->toBe($holder->getMorphClass())
        ->and((string) $binding->holder_id)->toBe((string) $holder->getKey())
        ->and($binding->reservation_operation_reference)->toBe('reservation:stored-value:1')
        ->and(StoredValueHolderBinding::query()->count())->toBe(1);
});

it('requires a caller supplied stable identity before a spend can reach Treasury', function (): void {
    $holder = actingAsTestUser();
    $voucher = storedValueVoucher();
    storedValueBinding($voucher, $holder);
    $operations = Mockery::mock(TreasuryAllocationOperationContract::class);
    $operations->shouldNotReceive('draw');

    $gateway = new WalletStoredValueExecutionGateway(
        allocationOperations: $operations,
        allocationReadModel: Mockery::mock(TreasuryAllocationReadModelContract::class),
        holderAuthority: Mockery::mock(StoredValueHolderAuthorityContract::class),
        destinationAuthority: Mockery::mock(StoredValueDestinationAuthorityContract::class),
        principalReferences: Mockery::mock(TreasuryPrincipalReferenceResolverContract::class),
    );

    expect(fn () => $gateway->spend(
        storedValueContext($voucher, null),
        2_500,
        'driver-generated-id',
    ))->toThrow(
        StoredValueSpendRejectedException::class,
        'caller-supplied stable execution identity',
    );
});

it('rejects activation replay by a different holder before Treasury mutation', function (): void {
    $firstHolder = actingAsTestUser();
    $secondHolder = actingAsTestUser();
    $voucher = storedValueVoucher();
    storedValueBinding($voucher, $firstHolder);
    $operations = Mockery::mock(TreasuryAllocationOperationContract::class);
    $operations->shouldNotReceive('activate');
    $holderAuthority = Mockery::mock(StoredValueHolderAuthorityContract::class);
    $holderAuthority->shouldReceive('authorize')->once()->andReturn(
        new StoredValueHolderAuthorityData(
            holder: $secondHolder,
            authorityReference: 'holder-authority:commissioned',
            principalReference: 'principal:holder:two',
        ),
    );
    $principalReferences = Mockery::mock(TreasuryPrincipalReferenceResolverContract::class);
    $principalReferences->shouldReceive('resolve')->once()->with($secondHolder)
        ->andReturn('principal:holder:two');
    $gateway = new WalletStoredValueExecutionGateway(
        allocationOperations: $operations,
        allocationReadModel: Mockery::mock(TreasuryAllocationReadModelContract::class),
        holderAuthority: $holderAuthority,
        destinationAuthority: new UnavailableStoredValueDestinationAuthority,
        principalReferences: $principalReferences,
    );

    expect(fn () => $gateway->activate(
        storedValueContext($voucher, 'activation-2'),
        'activation-2',
    ))->toThrow(
        StoredValueSpendRejectedException::class,
        'different immutable authority',
    );
});

it('keeps holder bindings immutable outside guarded lifecycle actions', function (): void {
    $holder = actingAsTestUser();
    $binding = storedValueBinding(storedValueVoucher(), $holder);

    expect(fn () => $binding->forceFill(['currency' => 'USD'])->save())
        ->toThrow(LogicException::class, 'guarded lifecycle actions')
        ->and(fn () => $binding->delete())
        ->toThrow(LogicException::class, 'cannot be deleted');
});

it('draws an authorized amount using the caller identity as replay scope', function (): void {
    $holder = actingAsTestUser();
    $voucher = storedValueVoucher();
    $binding = storedValueBinding($voucher, $holder);
    $operations = Mockery::mock(TreasuryAllocationOperationContract::class);
    $destinationAuthority = Mockery::mock(StoredValueDestinationAuthorityContract::class);

    $destinationAuthority->shouldReceive('authorize')->once()->andReturn(
        new StoredValueDestinationAuthorityData(
            counterpartyPositionReference: 'position:merchant:client-funds',
            authorityReference: 'merchant-authority:fare',
            principalReference: 'principal:merchant:one',
        ),
    );
    $operations->shouldReceive('draw')->once()->withArgs(function ($draw) use ($binding): bool {
        expect($draw->allocationReference)->toBe($binding->allocation_reference)
            ->and($draw->counterpartyPositionReference)->toBe('position:merchant:client-funds')
            ->and($draw->amountMinor)->toBe(2_500)
            ->and($draw->currency)->toBe('PHP')
            ->and($draw->operationReference)->toStartWith('stored-value-spend:')
            ->and($draw->idempotencyKey)->toBe($draw->operationReference)
            ->and($draw->externalReference)->not->toContain('fare-request-1');

        return true;
    })->andReturn(storedValueOperation(
        type: TreasuryAllocationOperationType::Draw,
        amountMinor: 2_500,
        balanceBeforeMinor: 100_000,
        balanceAfterMinor: 97_500,
    ));

    $gateway = new WalletStoredValueExecutionGateway(
        allocationOperations: $operations,
        allocationReadModel: Mockery::mock(TreasuryAllocationReadModelContract::class),
        holderAuthority: Mockery::mock(StoredValueHolderAuthorityContract::class),
        destinationAuthority: $destinationAuthority,
        principalReferences: Mockery::mock(TreasuryPrincipalReferenceResolverContract::class),
    );

    $result = $gateway->spend(
        storedValueContext($voucher, 'fare-request-1'),
        2_500,
        'fare-request-1',
    );

    expect($result)->toMatchArray([
        'stored_value_reference' => $binding->allocation_reference,
        'remaining_balance' => 97_500,
        'last_spend_execution_id' => 'fare-request-1',
    ]);
});

it('keeps replenishment fail closed until funding authority is commissioned', function (): void {
    $gateway = new WalletStoredValueExecutionGateway(
        allocationOperations: Mockery::mock(TreasuryAllocationOperationContract::class),
        allocationReadModel: Mockery::mock(TreasuryAllocationReadModelContract::class),
        holderAuthority: Mockery::mock(StoredValueHolderAuthorityContract::class),
        destinationAuthority: Mockery::mock(StoredValueDestinationAuthorityContract::class),
        principalReferences: Mockery::mock(TreasuryPrincipalReferenceResolverContract::class),
    );

    expect(fn () => $gateway->replenish(
        storedValueContext(storedValueVoucher(), 'top-up-1'),
        5_000,
        'top-up-1',
    ))->toThrow(
        StoredValueSpendRejectedException::class,
        'replenishment authority has not been commissioned',
    );
});

it('releases only the allocation remainder when an activated balance expires', function (): void {
    $holder = actingAsTestUser();
    $voucher = storedValueVoucher();
    $binding = storedValueBinding($voucher, $holder);
    $operations = Mockery::mock(TreasuryAllocationOperationContract::class);
    $operations->shouldReceive('release')->once()->withArgs(function ($release) use ($binding): bool {
        expect($release->allocationReference)->toBe($binding->allocation_reference)
            ->and($release->currency)->toBe('PHP')
            ->and($release->operationReference)->toStartWith('stored-value-expiry-release:');

        return true;
    })->andReturn(storedValueOperation(
        type: TreasuryAllocationOperationType::Release,
        amountMinor: 97_500,
        balanceBeforeMinor: 97_500,
        balanceAfterMinor: 0,
    ));
    $result = (new ReleasePayCodeTerminalReserve(
        accounting: app(TreasuryPayCodeAccountingService::class),
        journal: app(PayCodeTerminalReleaseJournal::class),
        allocationOperations: $operations,
    ))->handle($voucher, 'expired');

    expect($result->amountMinor)->toBe(97_500)
        ->and($result->terminalReason)->toBe('expired')
        ->and(data_get($voucher->refresh()->metadata, 'treasury.terminal_release.release_source'))
        ->toBe('stored_value_allocation_remainder')
        ->and($binding->refresh()->status)->toBe('released')
        ->and($binding->released_at)->not->toBeNull();
});

it('requires governed revocation instead of cancelling an activated balance', function (): void {
    $holder = actingAsTestUser();
    $voucher = storedValueVoucher();
    storedValueBinding($voucher, $holder);
    $operations = Mockery::mock(TreasuryAllocationOperationContract::class);
    $operations->shouldNotReceive('release');

    $action = new ReleasePayCodeTerminalReserve(
        accounting: app(TreasuryPayCodeAccountingService::class),
        journal: app(PayCodeTerminalReleaseJournal::class),
        allocationOperations: $operations,
    );

    expect(fn () => $action->handle($voucher, 'cancelled'))->toThrow(
        TreasuryConfigurationException::class,
        'requires a governed revocation',
    );
});

it('allows expiry to release an activated balance despite its activation claim', function (): void {
    $holder = actingAsTestUser();
    $voucher = storedValueVoucher();
    $voucher->forceFill(['expires_at' => now()->subMinute()])->saveQuietly();
    storedValueBinding($voucher, $holder);
    VoucherClaim::query()->create([
        'voucher_id' => $voucher->getKey(),
        'claim_number' => 1,
        'claim_type' => 'stored_value_activation',
        'status' => 'succeeded',
        'currency' => 'PHP',
        'reference' => 'stored-value-activation-claim:'.$voucher->getKey(),
    ]);
    $operations = Mockery::mock(TreasuryAllocationOperationContract::class);
    $operations->shouldReceive('release')->once()->andReturn(storedValueOperation(
        type: TreasuryAllocationOperationType::Release,
        amountMinor: 90_000,
        balanceBeforeMinor: 90_000,
        balanceAfterMinor: 0,
    ));
    $terminal = new ReleasePayCodeTerminalReserve(
        accounting: app(TreasuryPayCodeAccountingService::class),
        journal: app(PayCodeTerminalReleaseJournal::class),
        allocationOperations: $operations,
    );

    $result = (new ReleaseExpiredPayCodeReserve($terminal))->handle($voucher);

    expect($result->amountMinor)->toBe(90_000)
        ->and($result->terminalReason)->toBe('expired');
});

function storedValueVoucher(): Voucher
{
    return Voucher::query()->create([
        'code' => 'SV'.str()->upper(str()->random(4)),
        'metadata' => [
            'treasury' => [
                'pay_code_reservation' => [
                    'status' => 'reserved',
                    'operation_reference' => 'reservation:stored-value:1',
                    'amount_minor' => 100_000,
                    'currency' => 'PHP',
                ],
            ],
        ],
        'state' => 'active',
    ]);
}

function storedValueContext(Voucher $voucher, ?string $executionId): ExecutionContextData
{
    return new ExecutionContextData(
        contact: Contact::factory()->create(),
        voucherCode: $voucher->code,
        voucher: $voucher,
        instruction: new ExecutionInstructionData(
            driver: 'stored_value',
            metadata: [
                'stored_value' => [
                    'initial_balance' => 100_000,
                    'max_balance' => 100_000,
                    'replenishable' => false,
                ],
            ],
        ),
        correlation: $executionId === null ? [] : ['execution_id' => $executionId],
    );
}

function storedValueBinding(Voucher $voucher, $holder): StoredValueHolderBinding
{
    return StoredValueHolderBinding::query()->create([
        'voucher_id' => $voucher->getKey(),
        'allocation_reference' => 'stored-value-allocation:test-'.$voucher->getKey(),
        'reservation_operation_reference' => 'reservation:stored-value:1',
        'activation_operation_reference' => 'stored-value-activation:test-'.$voucher->getKey(),
        'holder_type' => $holder->getMorphClass(),
        'holder_id' => (string) $holder->getKey(),
        'holder_principal_reference' => 'principal:holder:one',
        'holder_authority_reference' => 'holder-authority:commissioned',
        'currency' => 'PHP',
        'activated_at' => now(),
    ]);
}

function storedValueReadModel(int $balanceMinor): TreasuryAllocationReadModelData
{
    return new TreasuryAllocationReadModelData(
        allocationReference: 'stored-value-allocation:test',
        currency: 'PHP',
        allocatedAmountMinor: 100_000,
        drawnAmountMinor: 100_000 - $balanceMinor,
        releasedAmountMinor: 0,
        outstandingAmountMinor: $balanceMinor,
        usableAmountMinor: $balanceMinor,
        sliceCount: 0,
        hasTreasuryFacts: true,
    );
}

function storedValueOperation(
    TreasuryAllocationOperationType $type,
    int $amountMinor,
    int $balanceBeforeMinor,
    int $balanceAfterMinor,
): TreasuryAllocationOperationData {
    return new TreasuryAllocationOperationData(
        operationReference: 'operation:stored-value:'.str()->uuid(),
        allocationReference: 'stored-value-allocation:test',
        operationType: $type,
        amountMinor: $amountMinor,
        currency: 'PHP',
        balanceBeforeMinor: $balanceBeforeMinor,
        balanceAfterMinor: $balanceAfterMinor,
        status: 'committed',
        idempotencyKey: 'idempotency:stored-value:'.str()->uuid(),
        externalReference: 'external:stored-value:test',
    );
}
