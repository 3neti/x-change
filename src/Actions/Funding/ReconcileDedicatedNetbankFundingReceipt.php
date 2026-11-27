<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use Illuminate\Support\Facades\DB;
use LBHurtado\EmiCore\Data\Funding\StandingFundingObservationRequestData;
use LBHurtado\EmiCore\Enums\FundingAddressPurpose;
use LBHurtado\XChange\Contracts\AuditLoggerContract;
use LBHurtado\XChange\Enums\FundingAddressStatus;
use LBHurtado\XChange\Enums\FundingRecognitionMode;
use LBHurtado\XChange\Exceptions\FundingSettlementDenied;
use LBHurtado\XChange\Models\AccountFundingReceipt;
use LBHurtado\XChange\Models\StandingFundingAddress;
use LBHurtado\XChange\Services\Funding\StandingFundingAddressBindingResolver;
use LBHurtado\XChange\Services\Funding\StandingFundingAddressProviderRegistry;
use LBHurtado\XChange\Services\Funding\StandingFundingLedgerWatermark;
use LBHurtado\XChange\Support\Funding\FundingDestinationSnapshot;

/** A narrowly scoped, idempotent reconciliation for a known dedicated-account receipt. */
final readonly class ReconcileDedicatedNetbankFundingReceipt
{
    public function __construct(
        private StandingFundingAddressProviderRegistry $providers,
        private StandingFundingAddressBindingResolver $bindings,
        private StandingFundingLedgerWatermark $watermark,
        private SyncStandingFundingAddress $sync,
        private AuditLoggerContract $audit,
    ) {}

    public function handle(
        StandingFundingAddress $address,
        string $providerTransactionId,
        int $expectedAmountMinor,
        string $payerEvidenceReference,
    ): AccountFundingReceipt {
        $providerTransactionId = trim($providerTransactionId);
        $payerEvidenceReference = trim($payerEvidenceReference);

        if ($providerTransactionId === '' || $expectedAmountMinor <= 0 || $payerEvidenceReference === '') {
            throw FundingSettlementDenied::because('the reconciliation details are incomplete');
        }
        if ($address->provider_code !== 'netbank'
            || $address->purpose !== FundingAddressPurpose::AccountFunding
            || $address->status !== FundingAddressStatus::Active
            || $address->recognition_mode !== FundingRecognitionMode::Automatic
            || ! $this->watermark->isDedicatedAutomatic($address)) {
            throw FundingSettlementDenied::because('the address is not an automatic dedicated NetBank funding address');
        }

        $binding = $this->bindings->current($address);
        $destination = is_array($binding->destinationSnapshot)
            ? FundingDestinationSnapshot::toData($binding->destinationSnapshot)
            : null;
        $observations = $this->providers->for('netbank')->observeStandingFundingAddress(
            new StandingFundingObservationRequestData(
                fundingAddress: $address->funding_address_ciphertext,
                accountReference: $binding->accountReference,
                purpose: $address->purpose,
                currency: $address->currency,
                verificationSource: 'operator-scoped-reconciliation',
                destination: $destination,
            ),
        );
        $target = collect($observations)->first(
            fn ($observation): bool => $observation->providerTransactionId === $providerTransactionId,
        );

        if ($target === null
            || strtolower(trim($target->providerStatus)) !== 'settled'
            || $target->currency !== 'PHP'
            || $target->grossAmountMinor !== $expectedAmountMinor
            || $target->netAmountMinor !== $expectedAmountMinor) {
            throw FundingSettlementDenied::because('the provider transaction no longer matches the scoped reconciliation evidence');
        }

        $this->watermark->captureExcept($address, [$providerTransactionId]);

        DB::transaction(function () use ($address, $providerTransactionId, $expectedAmountMinor, $payerEvidenceReference): void {
            $locked = StandingFundingAddress::query()->lockForUpdate()->findOrFail($address->getKey());
            $metadata = $locked->metadata ?? [];
            $metadata['scoped_reconciliation'] = [
                'provider_transaction_id' => $providerTransactionId,
                'amount_minor' => $expectedAmountMinor,
                'payer_evidence_reference_hash' => hash('sha256', $payerEvidenceReference),
                'authorized_at' => now()->toRfc3339String(),
            ];
            $locked->forceFill(['metadata' => $metadata])->saveQuietly();
        }, attempts: 3);

        $this->sync->handle($address->fresh(), 'operator-scoped-reconciliation');
        $receipt = AccountFundingReceipt::query()
            ->where('standing_funding_address_id', $address->getKey())
            ->where('provider_transaction_key', hash('sha256', "netbank\0".$providerTransactionId))
            ->first();

        if (! $receipt instanceof AccountFundingReceipt || $receipt->net_amount_minor !== $expectedAmountMinor) {
            throw FundingSettlementDenied::because('the scoped provider transaction was not recognized');
        }

        $this->audit->log('funding.standing_address.scoped_reconciliation_applied', [
            'standing_funding_address_reference' => $address->reference,
            'provider' => 'netbank',
            'provider_transaction_id' => $providerTransactionId,
            'amount_minor' => $expectedAmountMinor,
            'payer_evidence_reference_hash' => hash('sha256', $payerEvidenceReference),
            'account_funding_receipt_reference' => $receipt->reference,
        ]);

        return $receipt;
    }
}
