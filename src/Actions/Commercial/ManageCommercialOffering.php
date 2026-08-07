<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Commercial;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Contracts\CommercialLegalTraceResolverContract;
use LBHurtado\XChange\Contracts\CommercialOperatorAuthorityContract;
use LBHurtado\XChange\Enums\CommercialOfferingOrigin;
use LBHurtado\XChange\Enums\CommercialOfferingStatus;
use LBHurtado\XChange\Enums\CommercialOperatorCapability;
use LBHurtado\XChange\Models\CommercialOffering;
use LBHurtado\XCommerce\Data\CommercialOfferingData;

final class ManageCommercialOffering
{
    public function __construct(
        private readonly CommercialOperatorAuthorityContract $authority,
        private readonly CommercialLegalTraceResolverContract $legalTrace,
    ) {}

    public function createDraft(
        Model $maker,
        string $profile,
        CommercialOfferingData $offering,
    ): CommercialOffering {
        $this->authorize($maker, CommercialOperatorCapability::ManageOfferings);
        $offering = $this->legalTrace->forPublication($offering);

        return DB::transaction(function () use ($maker, $profile, $offering): CommercialOffering {
            $latestVersion = (int) CommercialOffering::query()
                ->where('reference', $offering->reference)
                ->lockForUpdate()
                ->max('version');

            if ($offering->version !== $latestVersion + 1) {
                throw new \DomainException('Commercial Offering version must follow the latest persisted version.');
            }

            return CommercialOffering::query()->create([
                'reference' => $offering->reference,
                'version' => $offering->version,
                'profile' => $profile,
                'status' => CommercialOfferingStatus::Draft,
                'origin' => CommercialOfferingOrigin::MakerCheckerRevision,
                'currency' => $offering->catalog->currency,
                'snapshot_hash' => $offering->snapshotHash(),
                'snapshot' => $offering->toArray(),
                'created_by_type' => $maker->getMorphClass(),
                'created_by_id' => $maker->getKey(),
                'effective_at' => $offering->effectiveAt,
            ]);
        });
    }

    public function submit(Model $maker, CommercialOffering $offering): CommercialOffering
    {
        $this->authorize($maker, CommercialOperatorCapability::ManageOfferings);

        if ($offering->status !== CommercialOfferingStatus::Draft
            || $offering->created_by_type !== $maker->getMorphClass()
            || (string) $offering->created_by_id !== (string) $maker->getKey()) {
            throw new \DomainException('Only the maker may submit their draft Commercial Offering.');
        }

        $offering->forceFill([
            'status' => CommercialOfferingStatus::PendingApproval,
            'submitted_by_type' => $maker->getMorphClass(),
            'submitted_by_id' => $maker->getKey(),
            'submitted_at' => now(),
        ])->save();

        return $offering->refresh();
    }

    public function publish(
        Model $checker,
        CommercialOffering $offering,
        string $authorizationReference,
    ): CommercialOffering {
        $this->authorize($checker, CommercialOperatorCapability::ApproveOfferings);

        return DB::transaction(function () use ($checker, $offering, $authorizationReference): CommercialOffering {
            $locked = CommercialOffering::query()->lockForUpdate()->findOrFail($offering->getKey());

            if ($locked->status !== CommercialOfferingStatus::PendingApproval) {
                throw new \DomainException('Only a submitted Commercial Offering may be published.');
            }

            if ($locked->created_by_type === $checker->getMorphClass()
                && (string) $locked->created_by_id === (string) $checker->getKey()) {
                throw new \DomainException('The Commercial Offering checker must be different from its maker.');
            }

            if (trim($authorizationReference) === '') {
                throw new \DomainException('A publication authorization reference is required.');
            }

            $locked->forceFill([
                'status' => CommercialOfferingStatus::Published,
                'approved_by_type' => $checker->getMorphClass(),
                'approved_by_id' => $checker->getKey(),
                'authorization_reference' => $authorizationReference,
                'approved_at' => now(),
            ])->save();

            return $locked->refresh();
        });
    }

    private function authorize(Model $operator, CommercialOperatorCapability $capability): void
    {
        if (! $this->authority->allows($operator, $capability)) {
            throw new AuthorizationException(
                "Operator lacks [{$capability->value}] authority.",
            );
        }
    }
}
