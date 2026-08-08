<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Commercial;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use JsonException;
use LBHurtado\Wallet\Contracts\SystemUserResolverContract;
use LBHurtado\XChange\Contracts\CommercialOperatorAuthorityContract;
use LBHurtado\XChange\Contracts\PayoutDestinationValidatorContract;
use LBHurtado\XChange\Data\Commercial\CommercialPartnerDestinationData;
use LBHurtado\XChange\Enums\CommercialOperatorCapability;
use LBHurtado\XChange\Enums\CommercialPartnerRevisionStatus;
use LBHurtado\XChange\Models\CommercialPartner;
use LBHurtado\XChange\Models\CommercialPartnerDestinationRevision;
use LBHurtado\XChange\Models\CommercialPartnerRevision;
use LBHurtado\XChange\Services\Commercial\CommercialGovernanceJournal;

final readonly class ManageCommercialPartnerDestination
{
    public function __construct(
        private CommercialOperatorAuthorityContract $authority,
        private SystemUserResolverContract $systemPrincipal,
        private PayoutDestinationValidatorContract $destinations,
        private CommercialGovernanceJournal $journal,
    ) {}

    /** @throws JsonException */
    public function createDraft(
        Model $maker,
        CommercialPartner $partner,
        CommercialPartnerDestinationData $data,
    ): CommercialPartnerDestinationRevision {
        $this->authorize($maker, CommercialOperatorCapability::ManagePartners);

        $partnerRevision = $partner->revisions()->approved()->first();

        if (! $partnerRevision instanceof CommercialPartnerRevision) {
            throw new \DomainException('An approved Commercial Partner revision is required before adding a destination.');
        }

        $normalizedAccount = preg_replace('/\D+/', '', $data->accountNumber) ?? '';
        $destination = $this->destinations->validate($data->bankCode, $normalizedAccount, 'INSTAPAY', $data->mobile);

        if ($destination->status === 'invalid') {
            throw new \DomainException($destination->message);
        }

        $payload = [
            'bank_code' => $destination->bankCode,
            'account_number' => $destination->accountNumber,
            'recipient_name' => $data->recipientName,
            'mobile' => $destination->mobile,
        ];
        $destinationHash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        $revision = DB::transaction(function () use (
            $data,
            $destinationHash,
            $maker,
            $partner,
            $partnerRevision,
            $payload,
        ): CommercialPartnerDestinationRevision {
            $version = ((int) CommercialPartnerDestinationRevision::query()
                ->whereBelongsTo($partner, 'partner')
                ->lockForUpdate()
                ->max('version')) + 1;

            return CommercialPartnerDestinationRevision::query()->create([
                'commercial_partner_id' => $partner->getKey(),
                'commercial_partner_revision_id' => $partnerRevision->getKey(),
                'version' => $version,
                'status' => CommercialPartnerRevisionStatus::Draft,
                'provider' => mb_strtolower($data->provider),
                'connection_reference' => $data->connectionReference,
                'currency' => mb_strtoupper($data->currency),
                'destination' => $payload,
                'destination_hash' => $destinationHash,
                'destination_summary' => $payload['bank_code'].' · ••••'.substr($payload['account_number'], -4),
                'maker_type' => $maker->getMorphClass(),
                'maker_id' => $maker->getKey(),
                'authorization_reference' => $data->authorizationReference,
            ]);
        }, attempts: 5);

        $this->journal->recordPartnerDestination($revision, 'commercial.partner_destination.drafted', $maker);

        return $revision;
    }

    public function submit(Model $maker, CommercialPartnerDestinationRevision $revision): CommercialPartnerDestinationRevision
    {
        $this->authorize($maker, CommercialOperatorCapability::ManagePartners);

        if ($revision->status !== CommercialPartnerRevisionStatus::Draft
            || $revision->maker_type !== $maker->getMorphClass()
            || (string) $revision->maker_id !== (string) $maker->getKey()) {
            throw new \DomainException('Only the maker may submit their destination revision.');
        }

        $revision->forceFill([
            'status' => CommercialPartnerRevisionStatus::AwaitingApproval,
            'submitted_at' => now(),
        ])->save();
        $this->journal->recordPartnerDestination($revision, 'commercial.partner_destination.submitted', $maker);

        return $revision->refresh();
    }

    public function approve(Model $checker, CommercialPartnerDestinationRevision $revision): CommercialPartnerDestinationRevision
    {
        $this->authorize($checker, CommercialOperatorCapability::ApprovePartners);

        $approved = DB::transaction(function () use ($checker, $revision): CommercialPartnerDestinationRevision {
            $locked = CommercialPartnerDestinationRevision::query()->lockForUpdate()->findOrFail($revision->getKey());

            if ($locked->status !== CommercialPartnerRevisionStatus::AwaitingApproval) {
                throw new \DomainException('Only a submitted destination revision may be approved.');
            }

            if ($locked->maker_type === $checker->getMorphClass()
                && (string) $locked->maker_id === (string) $checker->getKey()) {
                throw new \DomainException('The destination checker must be different from its maker.');
            }

            CommercialPartnerDestinationRevision::query()
                ->where('commercial_partner_id', $locked->commercial_partner_id)
                ->where('provider', $locked->provider)
                ->where('connection_reference', $locked->connection_reference)
                ->where('currency', $locked->currency)
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

            return $locked->refresh();
        }, attempts: 5);

        $this->journal->recordPartnerDestination($approved, 'commercial.partner_destination.approved', $checker);

        return $approved;
    }

    private function authorize(Model $operator, CommercialOperatorCapability $capability): void
    {
        if ($operator->is($this->systemPrincipal->resolve()) || ! $this->authority->allows($operator, $capability)) {
            throw new AuthorizationException("Operator lacks [{$capability->value}] authority.");
        }
    }
}
