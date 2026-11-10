<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use Composer\InstalledVersions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Contracts\CommercialComponentEconomicsResolverContract;
use LBHurtado\XChange\Contracts\CommercialLegalTraceResolverContract;
use LBHurtado\XChange\Contracts\CommercialOfferingResolverContract;
use LBHurtado\XChange\Contracts\CommercialOperatorAuthorityContract;
use LBHurtado\XChange\Enums\CommercialActivationAuthority;
use LBHurtado\XChange\Enums\CommercialOfferingOrigin;
use LBHurtado\XChange\Enums\CommercialOfferingStatus;
use LBHurtado\XChange\Enums\CommercialOperatorCapability;
use LBHurtado\XChange\Models\CommercialComponentEconomicsHead;
use LBHurtado\XChange\Models\CommercialOffering;
use LBHurtado\XChange\Models\CommercialOfferingActivation;
use LBHurtado\XChange\Models\CommercialOperatorAuthorization;
use LBHurtado\XCommerce\Data\CommercialCatalogData;
use LBHurtado\XCommerce\Data\CommercialCatalogItemData;
use LBHurtado\XCommerce\Data\CommercialComponentEconomicsData;
use LBHurtado\XCommerce\Data\CommercialComponentEconomicsSetData;
use LBHurtado\XCommerce\Data\CommercialOfferingData;

final readonly class CommissionDocumentPricing
{
    public function __construct(
        private CommercialOperatorAuthorityContract $authority,
        private CommercialLegalTraceResolverContract $legalTrace,
        private BootstrapCommercialComponentEconomicsFactory $bootstrapEconomics,
        private CommercialOfferingManifestCompiler $offeringManifests,
        private CommercialComponentEconomicsManifestCompiler $economicsManifests,
        private ActivateCommercialOffering $activateOffering,
        private PersistCommercialComponentEconomicsManifest $persistEconomics,
        private ActivateCommercialComponentEconomics $activateEconomics,
        private CommercialGovernanceJournal $journal,
        private CommercialOfferingResolverContract $offeringResolver,
        private CommercialComponentEconomicsResolverContract $economicsResolver,
    ) {}

    public function execute(Model $operator, string $authorizationReference): CommercialOfferingActivation
    {
        $authorizationReference = trim($authorizationReference);
        $authorized = CommercialOperatorAuthorization::query()
            ->where('operator_type', $operator->getMorphClass())
            ->where('operator_id', $operator->getKey())
            ->where('capability', CommercialOperatorCapability::CommissionDocumentPricing->value)
            ->where('authorization_reference', $authorizationReference)
            ->currentlyValid()->exists();
        if ($authorizationReference === '' || ! $authorized
            || ! $this->authority->allows($operator, CommercialOperatorCapability::CommissionDocumentPricing)) {
            throw new \DomainException('A designated operator and authorization reference are required.');
        }

        return DB::transaction(function () use ($operator, $authorizationReference): CommercialOfferingActivation {
            $active = CommercialOfferingActivation::query()->where('profile', 'pay_code')->whereNull('deactivated_at')
                ->lockForUpdate()->firstOrFail();
            if ($active->authority === CommercialActivationAuthority::SingleOperator
                && $active->activation_reference === $authorizationReference) {
                return $active;
            }
            $previous = $active->offering()->firstOrFail();
            $original = $this->offeringResolver->resolve('pay_code');
            if (collect($original->catalog->items)->contains(fn ($item): bool => $item->reference === 'inputs.fields.documents')) {
                throw new \DomainException('The active offering already contains document pricing.');
            }
            $head = CommercialComponentEconomicsHead::query()->with('currentActivation.economics')
                ->whereKey('pay_code')->lockForUpdate()->firstOrFail();
            $current = $head->currentActivation?->economics;
            if ($current === null || $current->commercial_offering_id !== $previous->getKey()) {
                throw new \DomainException('Active accounting does not match the current offering.');
            }
            $currentSet = $this->economicsResolver->resolve('pay_code');
            if ($original->catalog->currency !== 'PHP' || $currentSet->currency !== 'PHP') {
                throw new \DomainException('The document fee requires a PHP offering.');
            }
            $configuredItems = (array) config('x-commerce.catalogs.pay_code.items', []);
            $documentItem = CommercialCatalogItemData::fromArray(
                (array) ($configuredItems['inputs.fields.documents'] ?? []),
                'inputs.fields.documents',
                'PHP',
            );
            if ($documentItem->unitPriceMinor !== 200 || $documentItem->deprecated) {
                throw new \DomainException('The commissioned document fee must be the approved PHP 2.00.');
            }
            $catalogVersion = max($original->catalog->version, (int) config('x-commerce.catalogs.pay_code.version', 0)) + 1;
            $catalog = new CommercialCatalogData(
                $original->catalog->reference, $catalogVersion, $original->catalog->currency,
                [...$original->catalog->items, $documentItem],
            );
            $candidate = new CommercialOfferingData(
                $original->reference, $original->version, $catalog, $original->waterfallPolicy,
                $original->attributionPolicy, $original->legalTrace, now()->toIso8601String(),
            );
            $bootstrap = collect($this->bootstrapEconomics->make('pay_code', $candidate)->components);
            $baseEconomics = collect($currentSet->components)->firstWhere('componentReference', 'cash.amount');
            $bootstrapBase = $bootstrap->firstWhere('componentReference', 'cash.amount');
            if (! $baseEconomics instanceof CommercialComponentEconomicsData
                || ! $bootstrapBase instanceof CommercialComponentEconomicsData
                || $baseEconomics->toArray() !== $bootstrapBase->toArray()) {
                throw new \DomainException('Existing component economics differ from the configured commissioning policy.');
            }
            $documentEconomics = $bootstrap->firstWhere('componentReference', 'inputs.fields.documents');
            if (! $documentEconomics instanceof CommercialComponentEconomicsData) {
                throw new \DomainException('Document component economics could not be prepared.');
            }
            $economics = new CommercialComponentEconomicsSetData(
                $currentSet->reference, $currentSet->version + 1, $catalog->reference, $catalog->version, $catalog->currency,
                [...$currentSet->components, $documentEconomics],
            );
            $version = (int) CommercialOffering::query()->where('reference', $original->reference)->max('version') + 1;
            $offering = $this->legalTrace->forPublication(new CommercialOfferingData(
                $original->reference, $version, $catalog, $original->waterfallPolicy, $original->attributionPolicy,
                $original->legalTrace, now()->toIso8601String(),
            ));
            $manifest = $this->offeringManifests->compile('pay_code', $offering);
            $record = CommercialOffering::query()->create([
                'reference' => $offering->reference, 'version' => $offering->version, 'profile' => 'pay_code',
                'status' => CommercialOfferingStatus::Published, 'origin' => CommercialOfferingOrigin::SingleOperator,
                'currency' => $catalog->currency, 'snapshot_hash' => $offering->snapshotHash(), 'snapshot' => $offering->toArray(),
                'manifest_schema' => $manifest->schema, 'manifest_hash' => $manifest->hash, 'manifest_yaml' => $manifest->yaml,
                'source_package' => '3neti/x-change', 'source_package_version' => InstalledVersions::getPrettyVersion('3neti/x-change') ?? 'dev-source',
                'created_by_type' => $operator->getMorphClass(), 'created_by_id' => $operator->getKey(),
                'authorization_reference' => $authorizationReference, 'effective_at' => $offering->effectiveAt,
            ]);
            $this->journal->recordOffering($record, 'commercial.offering.single_operator_authorized', $operator, $authorizationReference);
            $economicsManifest = $this->economicsManifests->compile('pay_code', $offering, $manifest->hash, $economics);
            $activation = $this->activateOffering->execute($record, CommercialActivationAuthority::SingleOperator, $authorizationReference);
            $saved = $this->persistEconomics->execute(
                $record, $economicsManifest, $current->reference, $current->version + 1,
                CommercialOfferingOrigin::SingleOperator, CommercialActivationAuthority::SingleOperator,
            );
            $this->activateEconomics->execute($saved, CommercialActivationAuthority::SingleOperator,
                'document-economics:'.$economicsManifest->hash, actor: $operator, authorizationReference: $authorizationReference);

            return $activation;
        }, attempts: 5);
    }
}
