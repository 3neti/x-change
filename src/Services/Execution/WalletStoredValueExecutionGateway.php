<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Execution;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Exceptions\StoredValueSpendRejectedException;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryAllocationOperationContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryAllocationReadModelContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationActivationData;
use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationMovementData;
use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationReadModelData;
use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationReadModelQueryData;
use LBHurtado\Wallet\Treasury\Exceptions\TreasuryAllocationConflict;
use LBHurtado\XChange\Contracts\Execution\DurableStoredValueExecutionGatewayContract;
use LBHurtado\XChange\Contracts\Execution\StoredValueDestinationAuthorityContract;
use LBHurtado\XChange\Contracts\Execution\StoredValueHolderAuthorityContract;
use LBHurtado\XChange\Contracts\TreasuryPrincipalReferenceResolverContract;
use LBHurtado\XChange\Models\StoredValueHolderBinding;
use RuntimeException;

final readonly class WalletStoredValueExecutionGateway implements DurableStoredValueExecutionGatewayContract
{
    public function __construct(
        private TreasuryAllocationOperationContract $allocationOperations,
        private TreasuryAllocationReadModelContract $allocationReadModel,
        private StoredValueHolderAuthorityContract $holderAuthority,
        private StoredValueDestinationAuthorityContract $destinationAuthority,
        private TreasuryPrincipalReferenceResolverContract $principalReferences,
    ) {}

    public function activate(ExecutionContextData $context, string $executionId): array
    {
        $voucher = $this->voucher($context);
        $holder = $this->holderAuthority->authorize($context);
        $resolvedPrincipal = $this->principalReferences->resolve($holder->holder);

        if (! hash_equals($holder->principalReference, $resolvedPrincipal)) {
            throw new StoredValueSpendRejectedException(
                'Stored value holder authority does not match the persisted Account principal.',
            );
        }

        $reservation = $this->reservation($voucher);
        $initialAmountMinor = $this->instructionAmount($context, 'initial_balance');
        $maximumAmountMinor = $this->instructionAmount($context, 'max_balance');
        $replenishable = (bool) data_get(
            $context->instruction?->metadata,
            'stored_value.replenishable',
            false,
        );

        if ($initialAmountMinor !== $reservation['amount_minor']) {
            throw new StoredValueSpendRejectedException(
                'Stored value starting balance does not match its Treasury reservation.',
            );
        }

        $scope = $this->scope($voucher, $reservation['operation_reference']);
        $allocationReference = 'stored-value-allocation:'.$scope;
        $activationReference = 'stored-value-activation:'.$scope;

        try {
            return DB::transaction(function () use (
                $activationReference,
                $allocationReference,
                $executionId,
                $holder,
                $initialAmountMinor,
                $maximumAmountMinor,
                $replenishable,
                $reservation,
                $voucher,
            ): array {
                Voucher::query()->whereKey($voucher->getKey())->lockForUpdate()->firstOrFail();
                $existing = StoredValueHolderBinding::query()
                    ->where('voucher_id', $voucher->getKey())
                    ->lockForUpdate()
                    ->first();

                if ($existing instanceof StoredValueHolderBinding) {
                    $this->assertBindingReplay(
                        $existing,
                        $holder->holder,
                        $holder->authorityReference,
                        $holder->principalReference,
                        $allocationReference,
                        $reservation['operation_reference'],
                    );

                    return $this->state($existing, 'activation_execution_id', $executionId);
                }

                $this->allocationOperations->activate(
                    new TreasuryAllocationActivationData(
                        operationReference: $activationReference,
                        allocationReference: $allocationReference,
                        backingReservationOperationReference: $reservation['operation_reference'],
                        initialAmountMinor: $initialAmountMinor,
                        maximumAmountMinor: $maximumAmountMinor,
                        currency: $reservation['currency'],
                        replenishable: $replenishable,
                        idempotencyKey: $activationReference,
                        externalReference: 'stored-value-voucher:'.$voucher->getKey(),
                        metadata: [
                            'source' => 'x_change_stored_value_activation',
                            'holder_principal_reference' => $holder->principalReference,
                            'holder_authority_reference' => $holder->authorityReference,
                        ],
                    ),
                );

                $binding = StoredValueHolderBinding::query()->create([
                    'voucher_id' => $voucher->getKey(),
                    'allocation_reference' => $allocationReference,
                    'reservation_operation_reference' => $reservation['operation_reference'],
                    'activation_operation_reference' => $activationReference,
                    'holder_type' => $holder->holder->getMorphClass(),
                    'holder_id' => (string) $holder->holder->getKey(),
                    'holder_principal_reference' => $holder->principalReference,
                    'holder_authority_reference' => $holder->authorityReference,
                    'currency' => $reservation['currency'],
                    'activated_at' => now(),
                ]);

                return $this->state($binding, 'activation_execution_id', $executionId);
            }, attempts: 5);
        } catch (StoredValueSpendRejectedException $exception) {
            throw $exception;
        } catch (TreasuryAllocationConflict|RuntimeException $exception) {
            throw new StoredValueSpendRejectedException(
                'Stored value activation could not be committed safely.',
                previous: $exception,
            );
        }
    }

    public function spend(ExecutionContextData $context, int $amount, string $executionId): array
    {
        $callerExecutionId = $this->callerExecutionId($context, $executionId);

        if ($amount <= 0) {
            throw new StoredValueSpendRejectedException('Stored value spend amount must be positive.');
        }

        $binding = $this->binding($this->voucher($context));
        $destination = $this->destinationAuthority->authorize($context, $amount);
        $scope = hash('sha256', implode('|', [
            $binding->allocation_reference,
            'spend',
            $callerExecutionId,
        ]));

        try {
            $operation = $this->allocationOperations->draw(
                new TreasuryAllocationMovementData(
                    operationReference: 'stored-value-spend:'.$scope,
                    allocationReference: $binding->allocation_reference,
                    counterpartyPositionReference: $destination->counterpartyPositionReference,
                    amountMinor: $amount,
                    currency: $binding->currency,
                    idempotencyKey: 'stored-value-spend:'.$scope,
                    externalReference: 'stored-value-execution:'.hash('sha256', $callerExecutionId),
                    metadata: [
                        'source' => 'x_change_stored_value_spend',
                        'destination_authority_reference' => $destination->authorityReference,
                        'destination_principal_reference' => $destination->principalReference,
                    ],
                ),
            );
        } catch (TreasuryAllocationConflict|RuntimeException $exception) {
            throw new StoredValueSpendRejectedException(
                'Stored value spend could not be committed safely.',
                previous: $exception,
            );
        }

        return [
            'stored_value_reference' => $binding->allocation_reference,
            'remaining_balance' => $operation->balanceAfterMinor,
            'last_spend_execution_id' => $callerExecutionId,
            'operation_reference' => $operation->operationReference,
        ];
    }

    public function replenish(ExecutionContextData $context, int $amount, string $executionId): array
    {
        throw new StoredValueSpendRejectedException(
            'Stored value replenishment authority has not been commissioned.',
        );
    }

    public function balance(ExecutionContextData $context): int
    {
        $binding = $this->binding($this->voucher($context));

        return $this->read($binding)->usableAmountMinor;
    }

    private function voucher(ExecutionContextData $context): Voucher
    {
        $voucher = $context->voucher;

        if (! $voucher instanceof Voucher || ! $voucher->exists) {
            throw new StoredValueSpendRejectedException(
                'Stored value execution requires a persisted Pay Code.',
            );
        }

        return $voucher;
    }

    /**
     * @return array{operation_reference: string, amount_minor: int, currency: string}
     */
    private function reservation(Voucher $voucher): array
    {
        $reservation = data_get($voucher->metadata, 'treasury.pay_code_reservation');
        $operationReference = trim((string) data_get($reservation, 'operation_reference'));
        $amountMinor = (int) data_get($reservation, 'amount_minor', 0);
        $currency = mb_strtoupper(trim((string) data_get($reservation, 'currency')));

        if (
            ! is_array($reservation)
            || data_get($reservation, 'status') !== 'reserved'
            || $operationReference === ''
            || $amountMinor <= 0
            || $currency === ''
        ) {
            throw new StoredValueSpendRejectedException(
                'Stored value execution requires an active Treasury reservation.',
            );
        }

        return [
            'operation_reference' => $operationReference,
            'amount_minor' => $amountMinor,
            'currency' => $currency,
        ];
    }

    private function instructionAmount(ExecutionContextData $context, string $key): int
    {
        $amount = (int) data_get($context->instruction?->metadata, 'stored_value.'.$key, 0);

        if ($amount <= 0) {
            throw new StoredValueSpendRejectedException(
                'Stored value instructions are incomplete.',
            );
        }

        return $amount;
    }

    private function callerExecutionId(ExecutionContextData $context, string $executionId): string
    {
        $callerExecutionId = trim((string) data_get($context->correlation, 'execution_id'));

        if ($callerExecutionId === '' || ! hash_equals($callerExecutionId, $executionId)) {
            throw new StoredValueSpendRejectedException(
                'Stored value spend requires a caller-supplied stable execution identity.',
            );
        }

        return $callerExecutionId;
    }

    private function binding(Voucher $voucher): StoredValueHolderBinding
    {
        $binding = StoredValueHolderBinding::query()
            ->where('voucher_id', $voucher->getKey())
            ->where('status', 'active')
            ->first();

        if (! $binding instanceof StoredValueHolderBinding) {
            throw new StoredValueSpendRejectedException(
                'Stored value has not been activated by its holder.',
            );
        }

        return $binding;
    }

    private function state(
        StoredValueHolderBinding $binding,
        string $executionKey,
        string $executionId,
    ): array {
        return [
            'stored_value_reference' => $binding->allocation_reference,
            'remaining_balance' => $this->read($binding)->usableAmountMinor,
            $executionKey => $executionId,
        ];
    }

    private function read(StoredValueHolderBinding $binding): TreasuryAllocationReadModelData
    {
        $state = $this->allocationReadModel->read(
            new TreasuryAllocationReadModelQueryData(
                allocationReference: $binding->allocation_reference,
                currency: $binding->currency,
                metadata: ['source' => 'x_change_stored_value'],
            ),
        );

        if (! $state->hasTreasuryFacts) {
            throw new StoredValueSpendRejectedException(
                'Stored value Treasury facts are unavailable.',
            );
        }

        return $state;
    }

    private function assertBindingReplay(
        StoredValueHolderBinding $binding,
        Model $holder,
        string $authorityReference,
        string $principalReference,
        string $allocationReference,
        string $reservationReference,
    ): void {
        $matches = $binding->holder_type === $holder->getMorphClass()
            && (string) $binding->holder_id === (string) $holder->getKey()
            && hash_equals($binding->holder_authority_reference, $authorityReference)
            && hash_equals($binding->holder_principal_reference, $principalReference)
            && hash_equals($binding->allocation_reference, $allocationReference)
            && hash_equals($binding->reservation_operation_reference, $reservationReference)
            && $binding->status === 'active';

        if (! $matches) {
            throw new StoredValueSpendRejectedException(
                'Stored value is already bound to different immutable authority.',
            );
        }
    }

    private function scope(Voucher $voucher, string $reservationReference): string
    {
        return hash('sha256', implode('|', [
            'x-change.stored-value.v1',
            (string) $voucher->getKey(),
            $reservationReference,
        ]));
    }
}
