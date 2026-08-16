<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Enums\CommercialActivationAuthority;
use LBHurtado\XChange\Models\CommercialComponentEconomics;
use LBHurtado\XChange\Models\CommercialComponentEconomicsActivation;
use LBHurtado\XChange\Models\CommercialComponentEconomicsHead;
use LBHurtado\XChange\Models\CommercialOfferingActivation;

final readonly class ActivateCommercialComponentEconomics
{
    public function __construct(
        private CommercialComponentEconomicsManifestCompiler $manifests,
        private CommercialGovernanceJournal $journal,
    ) {}

    public function execute(
        CommercialComponentEconomics $economics,
        CommercialActivationAuthority $authority,
        string $activationReference,
        ?Model $actor = null,
        ?string $authorizationReference = null,
    ): CommercialComponentEconomicsActivation {
        $activationReference = trim($activationReference);
        if ($activationReference === '') {
            throw new \DomainException('Commercial Component Economics activation requires a stable reference.');
        }

        $activation = DB::transaction(function () use (
            $economics,
            $authority,
            $activationReference,
            $actor,
            $authorizationReference,
        ): CommercialComponentEconomicsActivation {
            $locked = CommercialComponentEconomics::query()
                ->with('offering')
                ->lockForUpdate()
                ->findOrFail($economics->getKey());

            if ($locked->effective_at?->isFuture()) {
                throw new \DomainException('Commercial Component Economics activation cannot precede its effective time.');
            }

            if ($locked->snapshot_hash !== $locked->economics()->snapshotHash()) {
                throw new \DomainException('Commercial Component Economics activation refused a snapshot hash mismatch.');
            }

            $manifest = $this->manifests->parse(
                $locked->artifact_yaml,
                $locked->offering->offering(),
                $locked->offering_manifest_hash,
            );
            if ($manifest->hash !== $locked->artifact_hash
                || $manifest->schema !== $locked->artifact_schema
                || $locked->offering_snapshot_hash !== $locked->offering->snapshot_hash) {
                throw new \DomainException('Commercial Component Economics activation refused inconsistent artifact evidence.');
            }

            $offeringActivation = CommercialOfferingActivation::query()
                ->where('profile', $locked->profile)
                ->whereNull('deactivated_at')
                ->lockForUpdate()
                ->first();
            if (! $offeringActivation instanceof CommercialOfferingActivation
                || $offeringActivation->commercial_offering_id !== $locked->commercial_offering_id
                || $offeringActivation->snapshot_hash !== $locked->offering_snapshot_hash) {
                throw new \DomainException('Commercial Component Economics must bind to the currently active Commercial Offering.');
            }

            CommercialComponentEconomicsHead::query()->insertOrIgnore([
                'profile' => $locked->profile,
                'current_activation_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $head = CommercialComponentEconomicsHead::query()
                ->whereKey($locked->profile)
                ->lockForUpdate()
                ->firstOrFail();

            $existing = CommercialComponentEconomicsActivation::query()
                ->where('profile', $locked->profile)
                ->where('activation_reference', $activationReference)
                ->lockForUpdate()
                ->first();
            if ($existing instanceof CommercialComponentEconomicsActivation) {
                if ($existing->commercial_component_economics_id !== $locked->getKey()
                    || $existing->authority !== $authority) {
                    throw new \DomainException('Commercial Component Economics activation reference conflicts with prior evidence.');
                }

                return $existing;
            }

            $created = CommercialComponentEconomicsActivation::query()->create([
                'profile' => $locked->profile,
                'commercial_component_economics_id' => $locked->getKey(),
                'previous_activation_id' => $head->current_activation_id,
                'authority' => $authority,
                'activation_reference' => $activationReference,
                'authorization_reference' => $authorizationReference,
                'actor_type' => $actor?->getMorphClass(),
                'actor_id' => $actor?->getKey(),
                'source_package' => $locked->source_package,
                'source_package_version' => $locked->source_package_version,
                'activated_at' => now(),
            ]);

            $head->forceFill(['current_activation_id' => $created->getKey()])->save();

            return $created;
        }, attempts: 5);

        $this->journal->recordComponentEconomicsActivation($activation);

        return $activation;
    }
}
