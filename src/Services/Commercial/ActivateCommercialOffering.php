<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Enums\CommercialActivationAuthority;
use LBHurtado\XChange\Enums\CommercialOfferingStatus;
use LBHurtado\XChange\Models\CommercialOffering;
use LBHurtado\XChange\Models\CommercialOfferingActivation;

final readonly class ActivateCommercialOffering
{
    public function __construct(private CommercialGovernanceJournal $journal) {}

    public function execute(
        CommercialOffering $offering,
        CommercialActivationAuthority $authority,
        string $activationReference,
    ): CommercialOfferingActivation {
        $activationReference = trim($activationReference);

        if ($activationReference === '') {
            throw new \DomainException('Commercial Offering activation requires a stable reference.');
        }

        $activation = DB::transaction(function () use ($offering, $authority, $activationReference): CommercialOfferingActivation {
            $locked = CommercialOffering::query()->lockForUpdate()->findOrFail($offering->getKey());

            if ($authority === CommercialActivationAuthority::IndependentApproval
                && $locked->status !== CommercialOfferingStatus::Published) {
                throw new \DomainException('Only an independently published Commercial Offering may be activated.');
            }

            if ($locked->effective_at?->isFuture()) {
                throw new \DomainException('Commercial Offering activation cannot precede its effective time.');
            }

            if ($locked->snapshot_hash !== $locked->offering()->snapshotHash()) {
                throw new \DomainException('Commercial Offering activation refused a snapshot hash mismatch.');
            }

            $existing = CommercialOfferingActivation::query()
                ->where('profile', $locked->profile)
                ->where('activation_reference', $activationReference)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof CommercialOfferingActivation) {
                if ($existing->commercial_offering_id !== $locked->getKey()
                    || $existing->snapshot_hash !== $locked->snapshot_hash) {
                    throw new \DomainException('Commercial Offering activation reference conflicts with prior evidence.');
                }

                return $existing;
            }

            CommercialOfferingActivation::query()
                ->where('profile', $locked->profile)
                ->whereNull('deactivated_at')
                ->lockForUpdate()
                ->get()
                ->each(function (CommercialOfferingActivation $activation) use ($locked): void {
                    $activation->forceFill(['deactivated_at' => now()])->save();

                    if ($activation->commercial_offering_id !== $locked->getKey()) {
                        $activation->offering?->forceFill([
                            'status' => CommercialOfferingStatus::Retired,
                            'retired_at' => now(),
                        ])->save();
                    }
                });

            return CommercialOfferingActivation::query()->create([
                'profile' => $locked->profile,
                'commercial_offering_id' => $locked->getKey(),
                'offering_reference' => $locked->reference,
                'offering_version' => $locked->version,
                'snapshot_hash' => $locked->snapshot_hash,
                'origin' => $locked->origin,
                'authority' => $authority,
                'activation_reference' => $activationReference,
                'source_package' => $locked->source_package,
                'source_package_version' => $locked->source_package_version,
                'activated_at' => now(),
            ]);
        }, attempts: 5);

        $this->journal->recordActivation($activation);

        return $activation;
    }
}
