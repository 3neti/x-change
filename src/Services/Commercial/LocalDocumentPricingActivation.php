<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Contracts\CommercialComponentEconomicsResolverContract;
use LBHurtado\XChange\Contracts\CommercialOfferingResolverContract;
use LBHurtado\XChange\Enums\CommercialActivationAuthority;
use LBHurtado\XChange\Enums\CommercialOfferingOrigin;
use LBHurtado\XChange\Enums\CommercialOfferingStatus;
use LBHurtado\XChange\Models\CommercialComponentEconomicsHead;
use LBHurtado\XChange\Models\CommercialOffering;
use LBHurtado\XChange\Models\CommercialOfferingActivation;
use LBHurtado\XCommerce\Data\CommercialCatalogData;
use LBHurtado\XCommerce\Data\CommercialCatalogItemData;
use LBHurtado\XCommerce\Data\CommercialComponentAllocationRuleData;
use LBHurtado\XCommerce\Data\CommercialComponentAllocationScheduleData;
use LBHurtado\XCommerce\Data\CommercialComponentEconomicsData;
use LBHurtado\XCommerce\Data\CommercialComponentEconomicsSetData;
use LBHurtado\XCommerce\Data\CommercialOfferingData;
use LBHurtado\XCommerce\Enums\CommercialAllocationDestinationKind;
use LBHurtado\XCommerce\Enums\CommercialWaterfallLineType;

final readonly class LocalDocumentPricingActivation
{
    public function __construct(
        private CommercialOfferingManifestCompiler $offeringManifests,
        private CommercialComponentEconomicsManifestCompiler $economicsManifests,
        private ActivateCommercialOffering $activateOffering,
        private PersistCommercialComponentEconomicsManifest $persistEconomics,
        private ActivateCommercialComponentEconomics $activateEconomics,
        private CommercialGovernanceJournal $journal,
        private CommercialOfferingResolverContract $offeringResolver,
        private CommercialComponentEconomicsResolverContract $economicsResolver,
    ) {}

    public function activate(string $authorizationReference): CommercialOfferingActivation
    {
        $database = DB::connection()->getDatabaseName();
        $localDatabase = realpath(database_path('database.sqlite'));
        if (! app()->environment(['local', 'testing']) || DB::connection()->getDriverName() !== 'sqlite'
            || ($database !== ':memory:' && ($localDatabase === false || realpath($database) !== $localDatabase))) {
            throw new \DomainException('This activation is limited to the local sandbox SQLite database.');
        }
        $authorizationReference = trim($authorizationReference);
        if ($authorizationReference === '') {
            throw new \DomainException('A local operator authorization reference is required.');
        }

        return DB::transaction(function () use ($authorizationReference): CommercialOfferingActivation {
            $active = CommercialOfferingActivation::query()->where('profile', 'pay_code')->whereNull('deactivated_at')
                ->lockForUpdate()->firstOrFail();
            if ($active->authority === CommercialActivationAuthority::LocalSandbox
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
                throw new \DomainException('The local document fee requires a PHP offering.');
            }
            $catalogVersion = max($original->catalog->version, (int) config('x-commerce.catalogs.pay_code.version', 0)) + 1;
            $catalog = new CommercialCatalogData(
                $original->catalog->reference, $catalogVersion, $original->catalog->currency,
                [...$original->catalog->items, new CommercialCatalogItemData('inputs.fields.documents', 'Document Upload', 'input_fields', 200, 'PHP')],
            );
            $documentEconomics = new CommercialComponentEconomicsData(
                componentReference: 'inputs.fields.documents', billingUnit: 'selected_instruction',
                billableEventReference: 'pay_code.issued_with_component', recognitionPolicyReference: 'recognition:pay-code-issuance:v1',
                capabilityReferences: [], allocationSchedule: new CommercialComponentAllocationScheduleData(
                    reference: 'component-allocation:pay_code:inputs.fields.documents', version: 1, currency: 'PHP',
                    rules: [new CommercialComponentAllocationRuleData(
                        reference: 'sandbox-document-revenue', sequence: 10, lineType: CommercialWaterfallLineType::Allocation,
                        category: 'commercial_revenue', destinationKind: CommercialAllocationDestinationKind::InternalOwner,
                        recipientReference: 'institution:x-change', participantRole: 'institution', fixedAmountMinor: 200,
                    )],
                ), nonBillableReason: null,
            );
            $economics = new CommercialComponentEconomicsSetData(
                $currentSet->reference, $currentSet->version + 1, $catalog->reference, $catalog->version, $catalog->currency,
                [...$currentSet->components, $documentEconomics],
            );
            $version = (int) CommercialOffering::query()->where('reference', $original->reference)->max('version') + 1;
            $offering = new CommercialOfferingData(
                $original->reference, $version, $catalog, $original->waterfallPolicy, $original->attributionPolicy,
                $original->legalTrace, now()->toIso8601String(),
            );
            $manifest = $this->offeringManifests->compile('pay_code', $offering);
            $record = CommercialOffering::query()->create([
                'reference' => $offering->reference, 'version' => $offering->version, 'profile' => 'pay_code',
                'status' => CommercialOfferingStatus::Published, 'origin' => CommercialOfferingOrigin::LocalSandbox,
                'currency' => $catalog->currency, 'snapshot_hash' => $offering->snapshotHash(), 'snapshot' => $offering->toArray(),
                'manifest_schema' => $manifest->schema, 'manifest_hash' => $manifest->hash, 'manifest_yaml' => $manifest->yaml,
                'source_package' => '3neti/x-change', 'source_package_version' => 'local-sandbox',
                'authorization_reference' => $authorizationReference, 'effective_at' => $offering->effectiveAt,
            ]);
            $this->journal->recordOffering($record, 'commercial.offering.local_sandbox_authorized', 'local-sandbox-operator', $authorizationReference);
            $economicsManifest = $this->economicsManifests->compile('pay_code', $offering, $manifest->hash, $economics);
            $activation = $this->activateOffering->execute($record, CommercialActivationAuthority::LocalSandbox, $authorizationReference);
            $saved = $this->persistEconomics->execute(
                $record, $economicsManifest, $current->reference, $current->version + 1,
                CommercialOfferingOrigin::LocalSandbox, CommercialActivationAuthority::LocalSandbox,
            );
            $this->activateEconomics->execute($saved, CommercialActivationAuthority::LocalSandbox,
                'local-document-economics:'.$economicsManifest->hash, authorizationReference: $authorizationReference);

            return $activation;
        }, attempts: 5);
    }
}
