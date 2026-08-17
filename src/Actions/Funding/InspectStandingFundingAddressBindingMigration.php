<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use LBHurtado\EmiCore\Enums\FundingAddressPurpose;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\XChange\Contracts\FundingAccountCreditContract;
use LBHurtado\XChange\Enums\AccountFundingReceiptStatus;
use LBHurtado\XChange\Enums\FundingAddressStatus;
use LBHurtado\XChange\Exceptions\FundingSettlementDenied;
use LBHurtado\XChange\Models\AccountFundingReceipt;
use LBHurtado\XChange\Models\FundingSuspenseCase;
use LBHurtado\XChange\Models\StandingFundingAddress;
use LBHurtado\XChange\Models\StandingFundingAddressBindingMigration;
use LBHurtado\XChange\Services\Funding\StandingFundingAccountReferenceResolver;
use LBHurtado\XChange\Services\Funding\StandingFundingAddressBindingResolver;
use LBHurtado\XChange\Services\Funding\StandingFundingDestinationResolver;
use LBHurtado\XChange\Support\Funding\FundingDestinationSnapshot;

final readonly class InspectStandingFundingAddressBindingMigration
{
    public function __construct(
        private StandingFundingAddressBindingResolver $bindings,
        private StandingFundingAccountReferenceResolver $accounts,
        private StandingFundingDestinationResolver $destinations,
        private FundingAccountCreditContract $fundingAccounts,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(
        StandingFundingAddress $address,
        ?CarbonImmutable $effectiveAt = null,
        ?int $ignoredMigrationId = null,
    ): array {
        $address->loadMissing('owner');
        $owner = $address->owner;

        if (! $owner instanceof Model) {
            throw new \DomainException('The Standing Funding Address owner cannot be resolved.');
        }

        $current = $this->bindings->current($address);
        $targetReference = $this->accounts->resolve($owner, $address->provider_code, $address->currency);
        $targetDestination = $this->destinations->resolve($owner, $targetReference);
        $targetSnapshot = FundingDestinationSnapshot::fromData($targetDestination);
        $targetBindingKey = $this->bindingKey($owner, $targetReference, $address);
        $receiptCounts = AccountFundingReceipt::query()
            ->whereBelongsTo($address)
            ->selectRaw('status, COUNT(*) AS aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();
        $inFlightStatuses = [
            AccountFundingReceiptStatus::Observed->value,
            AccountFundingReceiptStatus::AwaitingApproval->value,
            AccountFundingReceiptStatus::Ready->value,
            AccountFundingReceiptStatus::Suspense->value,
        ];
        $inFlightReceiptCount = array_sum(Arr::only($receiptCounts, $inFlightStatuses));
        $observationIds = AccountFundingReceipt::query()
            ->whereBelongsTo($address)
            ->pluck('provider_funding_observation_id');
        $unattributedObservationCount = ProviderFundingObservation::query()
            ->where('provider_code', $address->provider_code)
            ->where('funding_address', 'sha256:'.$address->funding_address_hash)
            ->whereNotIn('id', $observationIds)
            ->count();
        $openSuspenseCount = FundingSuspenseCase::query()
            ->where('status', 'open')
            ->whereIn('provider_funding_observation_id', $observationIds)
            ->count();
        $oldOwnerMatches = $this->belongsTo($current->accountReference, $owner);
        $newOwnerMatches = $this->belongsTo($targetReference, $owner);
        $derivationMatches = hash_equals(
            $address->funding_address_hash,
            hash('sha256', (string) $address->funding_address_ciphertext),
        );
        $competingRequests = StandingFundingAddressBindingMigration::query()
            ->whereBelongsTo($address)
            ->whereIn('status', ['awaiting_approval', 'approved']);

        if ($ignoredMigrationId !== null) {
            $competingRequests->whereKeyNot($ignoredMigrationId);
        }

        $competingRequestCount = $competingRequests->count();
        $alreadyCurrent = hash_equals($current->bindingKey, $targetBindingKey)
            && hash_equals(
                hash('sha256', $current->accountReference),
                hash('sha256', $targetReference),
            );
        $predicates = [
            'address_active' => $address->status === FundingAddressStatus::Active,
            'account_funding_purpose' => $address->purpose === FundingAddressPurpose::AccountFunding,
            'former_ledger_resolves_to_owner' => $oldOwnerMatches,
            'target_client_funds_resolves_to_owner' => $newOwnerMatches,
            'provider_currency_purpose_unchanged' => true,
            'funding_address_derivation_matches' => $derivationMatches,
            'no_in_flight_receipts' => $inFlightReceiptCount === 0,
            'no_unattributed_provider_observations' => $unattributedObservationCount === 0,
            'no_open_suspense_cases' => $openSuspenseCount === 0,
            'no_competing_binding_migration' => $competingRequestCount === 0,
            'binding_requires_migration' => ! $alreadyCurrent,
        ];
        $effectiveAt ??= now()->addHour();
        $evidence = [
            'schema' => 'x-change.funding-standing-address-binding-migration-evidence.v1',
            'standing_funding_address_reference' => $address->reference,
            'owner_reference_hash' => hash('sha256', $owner->getMorphClass().':'.$owner->getKey()),
            'provider' => $address->provider_code,
            'purpose' => $address->purpose->value,
            'currency' => $address->currency,
            'derivation_scheme' => $address->derivation_scheme,
            'funding_address_hash' => $address->funding_address_hash,
            'from_account_reference_hash' => hash('sha256', $current->accountReference),
            'to_account_reference_hash' => hash('sha256', $targetReference),
            'from_binding_key' => $current->bindingKey,
            'to_binding_key' => $targetBindingKey,
            'current_binding_version' => $current->version,
            'receipt_counts' => $receiptCounts,
            'receipt_count' => array_sum($receiptCounts),
            'in_flight_receipt_count' => $inFlightReceiptCount,
            'unattributed_observation_count' => $unattributedObservationCount,
            'open_suspense_count' => $openSuspenseCount,
            'qr_artifact_count' => $address->qrArtifacts()->count(),
            'proposed_effective_at' => $effectiveAt->toRfc3339String(),
            'predicates' => $predicates,
            'provider_calls' => false,
            'qr_regenerated' => false,
            'inventory_changed' => false,
        ];

        return [
            'safe' => ! in_array(false, $predicates, true),
            'evidence' => $evidence,
            'evidence_hash' => hash('sha256', json_encode($evidence, JSON_THROW_ON_ERROR)),
            'current_account_reference' => $current->accountReference,
            'target_account_reference' => $targetReference,
            'target_binding_key' => $targetBindingKey,
            'target_destination_snapshot' => $targetSnapshot,
            'target_destination_fingerprint' => $targetDestination->fingerprint,
        ];
    }

    private function bindingKey(
        Model $owner,
        string $accountReference,
        StandingFundingAddress $address,
    ): string {
        return hash('sha256', implode("\0", [
            $owner::class.':'.$owner->getKey(),
            $accountReference,
            $address->provider_code,
            $address->purpose->value,
            $address->currency,
        ]));
    }

    private function belongsTo(string $accountReference, Model $owner): bool
    {
        try {
            $account = $this->fundingAccounts->resolve($accountReference);
        } catch (FundingSettlementDenied) {
            return false;
        }

        $holder = data_get($account, 'holder');

        return $holder instanceof Model
            && $holder->getMorphClass() === $owner->getMorphClass()
            && (string) $holder->getKey() === (string) $owner->getKey();
    }
}
