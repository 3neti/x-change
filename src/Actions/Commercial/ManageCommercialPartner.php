<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Commercial;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use JsonException;
use LBHurtado\Wallet\Contracts\SystemUserResolverContract;
use LBHurtado\XChange\Contracts\CommercialOperatorAuthorityContract;
use LBHurtado\XChange\Data\Commercial\CommercialPartnerRevisionData;
use LBHurtado\XChange\Enums\CommercialOperatorCapability;
use LBHurtado\XChange\Enums\CommercialPartnerRevisionStatus;
use LBHurtado\XChange\Enums\CommercialPartnerStatus;
use LBHurtado\XChange\Models\CommercialPartner;
use LBHurtado\XChange\Models\CommercialPartnerRevision;
use LBHurtado\XChange\Services\Commercial\CommercialGovernanceJournal;

final readonly class ManageCommercialPartner
{
    public function __construct(
        private CommercialOperatorAuthorityContract $authority,
        private SystemUserResolverContract $systemPrincipal,
        private CommercialGovernanceJournal $journal,
    ) {}

    /** @throws JsonException */
    public function createDraft(Model $maker, CommercialPartnerRevisionData $data): CommercialPartnerRevision
    {
        $this->authorize($maker, CommercialOperatorCapability::ManagePartners);
        $snapshot = $data->toArray();
        $snapshotHash = hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        $revision = DB::transaction(function () use ($data, $maker, $snapshotHash): CommercialPartnerRevision {
            $partner = CommercialPartner::query()
                ->where('reference', $data->reference)
                ->lockForUpdate()
                ->first();

            if (! $partner instanceof CommercialPartner) {
                $partner = CommercialPartner::query()->create([
                    'reference' => $data->reference,
                    'display_name' => $data->displayName,
                    'status' => CommercialPartnerStatus::Draft,
                    'created_by_type' => $maker->getMorphClass(),
                    'created_by_id' => $maker->getKey(),
                ]);
            }

            if (CommercialPartnerRevision::query()
                ->whereBelongsTo($partner, 'partner')
                ->whereIn('status', [
                    CommercialPartnerRevisionStatus::Draft,
                    CommercialPartnerRevisionStatus::AwaitingApproval,
                ])
                ->exists()) {
                throw new \DomainException('Commercial Partner already has an open revision.');
            }

            $version = ((int) CommercialPartnerRevision::query()
                ->whereBelongsTo($partner, 'partner')
                ->max('version')) + 1;

            return CommercialPartnerRevision::query()->create([
                'commercial_partner_id' => $partner->getKey(),
                'version' => $version,
                'status' => CommercialPartnerRevisionStatus::Draft,
                'display_name' => $data->displayName,
                'legal_name' => $data->legalName,
                'external_reference' => $data->externalReference,
                'attribution_basis' => $data->attributionBasis,
                'authorization_reference' => $data->authorizationReference,
                'terms' => $data->terms,
                'snapshot_hash' => $snapshotHash,
                'maker_type' => $maker->getMorphClass(),
                'maker_id' => $maker->getKey(),
            ]);
        }, attempts: 5);

        $this->journal->recordPartner($revision, 'commercial.partner.drafted', $maker);

        return $revision;
    }

    public function submit(Model $maker, CommercialPartnerRevision $revision): CommercialPartnerRevision
    {
        $this->authorize($maker, CommercialOperatorCapability::ManagePartners);

        if ($revision->status !== CommercialPartnerRevisionStatus::Draft
            || $revision->maker_type !== $maker->getMorphClass()
            || (string) $revision->maker_id !== (string) $maker->getKey()) {
            throw new \DomainException('Only the maker may submit their Commercial Partner revision.');
        }

        $revision->forceFill([
            'status' => CommercialPartnerRevisionStatus::AwaitingApproval,
            'submitted_at' => now(),
        ])->save();

        if ($revision->partner->status === CommercialPartnerStatus::Draft) {
            $revision->partner->forceFill([
                'status' => CommercialPartnerStatus::AwaitingApproval,
                'submitted_at' => now(),
            ])->save();
        }

        $this->journal->recordPartner($revision, 'commercial.partner.submitted', $maker);

        return $revision->refresh();
    }

    public function approve(Model $checker, CommercialPartnerRevision $revision): CommercialPartnerRevision
    {
        $this->authorize($checker, CommercialOperatorCapability::ApprovePartners);

        $approved = DB::transaction(function () use ($checker, $revision): CommercialPartnerRevision {
            $locked = CommercialPartnerRevision::query()->lockForUpdate()->findOrFail($revision->getKey());

            if ($locked->status !== CommercialPartnerRevisionStatus::AwaitingApproval) {
                throw new \DomainException('Only a submitted Commercial Partner revision may be approved.');
            }

            if ($locked->maker_type === $checker->getMorphClass()
                && (string) $locked->maker_id === (string) $checker->getKey()) {
                throw new \DomainException('The Commercial Partner checker must be different from its maker.');
            }

            CommercialPartnerRevision::query()
                ->where('commercial_partner_id', $locked->commercial_partner_id)
                ->where('status', CommercialPartnerRevisionStatus::Approved)
                ->update([
                    'status' => CommercialPartnerRevisionStatus::Superseded,
                    'superseded_at' => now(),
                ]);

            $locked->forceFill([
                'status' => CommercialPartnerRevisionStatus::Approved,
                'checker_type' => $checker->getMorphClass(),
                'checker_id' => $checker->getKey(),
                'approved_at' => now(),
                'effective_at' => now(),
            ])->save();

            $locked->partner->forceFill([
                'display_name' => $locked->display_name,
                'status' => CommercialPartnerStatus::Active,
                'activated_at' => now(),
                'suspended_at' => null,
            ])->save();

            return $locked->refresh();
        }, attempts: 5);

        $this->journal->recordPartner($approved, 'commercial.partner.approved', $checker);

        return $approved;
    }

    private function authorize(Model $operator, CommercialOperatorCapability $capability): void
    {
        if ($operator->is($this->systemPrincipal->resolve()) || ! $this->authority->allows($operator, $capability)) {
            throw new AuthorizationException("Operator lacks [{$capability->value}] authority.");
        }
    }
}
