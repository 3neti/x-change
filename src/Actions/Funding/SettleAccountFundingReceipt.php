<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use Bavix\Wallet\Models\Transaction;
use Illuminate\Support\Facades\DB;
use LBHurtado\EmiCore\Enums\FundingAddressPurpose;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryOperationContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryData;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryRecognitionData;
use LBHurtado\XChange\Contracts\AuditLoggerContract;
use LBHurtado\XChange\Contracts\FundingAccountCreditContract;
use LBHurtado\XChange\Enums\AccountFundingReceiptStatus;
use LBHurtado\XChange\Enums\FundingAddressStatus;
use LBHurtado\XChange\Events\FundingProjectionChanged;
use LBHurtado\XChange\Exceptions\FundingSettlementDenied;
use LBHurtado\XChange\Models\AccountFundingReceipt;
use LBHurtado\XChange\Models\StandingFundingAddress;
use LBHurtado\XChange\Services\Funding\StandingFundingRecognitionPolicy;

final class SettleAccountFundingReceipt
{
    public function __construct(
        private readonly TreasuryInventoryOperationContract $treasury,
        private readonly FundingAccountCreditContract $accounts,
        private readonly AuditLoggerContract $audit,
        private readonly StandingFundingRecognitionPolicy $recognitionPolicy,
    ) {}

    public function handle(AccountFundingReceipt $receipt): AccountFundingReceipt
    {
        [$settled, $newlySettled] = DB::transaction(function () use ($receipt): array {
            $addressId = (int) $receipt->standing_funding_address_id;
            $address = StandingFundingAddress::query()->lockForUpdate()->findOrFail($addressId);
            $locked = AccountFundingReceipt::query()->lockForUpdate()->findOrFail($receipt->getKey());

            if ($locked->status === AccountFundingReceiptStatus::Settled) {
                return [$locked, false];
            }

            if ($locked->status !== AccountFundingReceiptStatus::Ready) {
                throw FundingSettlementDenied::because('the Account Funding Receipt is not ready');
            }

            $observation = ProviderFundingObservation::query()
                ->whereKey($locked->provider_funding_observation_id)
                ->lockForUpdate()
                ->first();

            $this->assertSettlementEvidence($address, $locked, $observation);
            $this->assertDailyLimit($address, $locked);
            $treasury = $this->treasuryConfiguration($locked->provider_code);
            $operationReference = 'standing-funding-recognition:'.hash('sha256', $locked->reference);

            $this->treasury->registerInventory(new TreasuryInventoryData(
                inventoryReference: $treasury['inventory_reference'],
                resourceType: $treasury['resource_type'],
                currency: $locked->currency,
                capacityMinor: 0,
                status: 'requested',
                idempotencyKey: 'register:'.$treasury['inventory_reference'],
                externalReference: $treasury['settlement_resource_reference'],
                metadata: [
                    'provider' => $locked->provider_code,
                    'source' => 'x-change.standing-funding-address',
                ],
            ));

            $recognition = $this->treasury->recognize(new TreasuryInventoryRecognitionData(
                operationReference: $operationReference,
                inventoryReference: $treasury['inventory_reference'],
                settlementResourceReference: $treasury['settlement_resource_reference'],
                amountMinor: $locked->net_amount_minor,
                currency: $locked->currency,
                status: 'requested',
                idempotencyKey: 'standing-funding-recognition-key:'.hash('sha256', $locked->reference),
                effectiveAt: (
                    $observation->settledAtInstant()
                    ?? $observation->occurredAtInstant()
                )?->toRfc3339String(),
                externalReference: $locked->provider_code.':'.$observation->provider_transaction_id,
                metadata: [
                    'account_funding_receipt_reference' => $locked->reference,
                    'standing_funding_address_reference' => $address->reference,
                    'provider_observation_id' => $observation->getKey(),
                    'gross_amount_minor' => $locked->gross_amount_minor,
                    'fee_amount_minor' => $locked->fee_amount_minor,
                ],
            ));

            $account = $this->accounts->resolve($locked->account_reference);
            $transaction = $this->accounts->credit($account, $locked->net_amount_minor, [
                'source' => 'verified_provider_funding',
                'funding_mode' => 'standing_address',
                'provider_status_at_recognition' => $observation->provider_status,
                'provisional_recognition' => $this->recognitionPolicy->isProvisional($observation),
                'account_funding_receipt_reference' => $locked->reference,
                'standing_funding_address_reference' => $address->reference,
                'provider' => $locked->provider_code,
                'provider_observation_id' => $observation->getKey(),
                'treasury_operation_reference' => $recognition->operationReference,
            ]);

            if (! $transaction instanceof Transaction) {
                throw FundingSettlementDenied::because('the Account ledger did not return a wallet transaction');
            }

            $locked->status = AccountFundingReceiptStatus::Settled;
            $locked->treasury_inventory_reference = $treasury['inventory_reference'];
            $locked->treasury_operation_reference = $recognition->operationReference;
            $locked->wallet_transaction_id = $transaction->getKey();
            $locked->wallet_transaction_uuid = $transaction->uuid;
            $locked->settled_at = now();
            $locked->metadata = array_merge($locked->metadata ?? [], [
                'provider_status_at_recognition' => $observation->provider_status,
                'provisional_recognition' => $this->recognitionPolicy->isProvisional($observation),
            ]);
            $locked->saveQuietly();

            return [$locked->refresh(), true];
        }, attempts: 5);

        $this->audit->log('funding.standing_address.account_credited', [
            'account_funding_receipt_reference' => $settled->reference,
            'standing_funding_address_id' => $settled->standing_funding_address_id,
            'provider' => $settled->provider_code,
            'net_amount_minor' => $settled->net_amount_minor,
            'currency' => $settled->currency,
            'provider_status_at_recognition' => data_get(
                $settled->metadata,
                'provider_status_at_recognition',
            ),
            'provisional_recognition' => data_get(
                $settled->metadata,
                'provisional_recognition',
                false,
            ),
        ]);

        if ($newlySettled
            && (bool) config('x-change.funding.broadcast_enabled', true)) {
            $address = StandingFundingAddress::query()->findOrFail(
                $settled->standing_funding_address_id,
            );
            FundingProjectionChanged::dispatch(
                ownerType: $address->owner_type,
                ownerId: (string) $address->owner_id,
                receiptReference: $settled->reference,
                occurredAt: $settled->settled_at?->toRfc3339String()
                    ?? now()->toRfc3339String(),
            );
        }

        return $settled;
    }

    private function assertSettlementEvidence(
        StandingFundingAddress $address,
        AccountFundingReceipt $receipt,
        ?ProviderFundingObservation $observation,
    ): void {
        $matches = $address->status === FundingAddressStatus::Active
            && $address->purpose === FundingAddressPurpose::AccountFunding
            && $receipt->purpose === FundingAddressPurpose::AccountFunding
            && $receipt->standing_funding_address_id === $address->getKey()
            && $receipt->provider_code === $address->provider_code
            && $receipt->account_reference === $address->account_reference
            && $observation instanceof ProviderFundingObservation
            && $observation->provider_code === $address->provider_code
            && $this->recognitionPolicy->accepts($observation)
            && $observation->currency === $address->currency
            && $observation->gross_amount_minor === $receipt->gross_amount_minor
            && $observation->fee_amount_minor === $receipt->fee_amount_minor
            && $observation->net_amount_minor === $receipt->net_amount_minor
            && $observation->net_amount_minor > 0
            && $observation->occurredAtInstant() !== null
            && $address->activated_at !== null
            && $observation->occurredAtInstant()->greaterThanOrEqualTo($address->activated_at)
            && $observation->funding_address === 'sha256:'.$address->funding_address_hash
            && data_get($observation->metadata, 'destination_verified') === true;

        if (! $matches) {
            throw FundingSettlementDenied::because(
                'authoritative provider evidence does not match the Standing Funding Address',
            );
        }
    }

    private function assertDailyLimit(
        StandingFundingAddress $address,
        AccountFundingReceipt $receipt,
    ): void {
        if ($address->daily_limit_minor === null) {
            return;
        }

        $recognizedToday = AccountFundingReceipt::query()
            ->where('standing_funding_address_id', $address->getKey())
            ->where('status', AccountFundingReceiptStatus::Settled)
            ->where('settled_at', '>=', now()->startOfDay())
            ->whereKeyNot($receipt->getKey())
            ->sum('gross_amount_minor');

        if ($recognizedToday + $receipt->gross_amount_minor > $address->daily_limit_minor) {
            throw FundingSettlementDenied::because('the Standing Funding Address daily limit would be exceeded');
        }
    }

    /**
     * @return array{inventory_reference: string, settlement_resource_reference: string, resource_type: string}
     */
    private function treasuryConfiguration(string $provider): array
    {
        $configuration = config("x-change.funding.providers.{$provider}.treasury");

        if (! is_array($configuration)) {
            throw FundingSettlementDenied::because('the provider Treasury Inventory is not configured');
        }

        foreach (['inventory_reference', 'settlement_resource_reference', 'resource_type'] as $key) {
            if (! is_string($configuration[$key] ?? null) || trim($configuration[$key]) === '') {
                throw FundingSettlementDenied::because("the provider Treasury {$key} is not configured");
            }
        }

        return $configuration;
    }
}
