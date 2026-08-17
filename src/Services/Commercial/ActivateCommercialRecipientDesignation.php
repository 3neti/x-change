<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Models\CommercialRecipientDesignation;
use LBHurtado\XChange\Support\Time\UtcInstant;
use LBHurtado\XProvisioning\Data\CommercialRecipientDesignationData;

final readonly class ActivateCommercialRecipientDesignation
{
    public function __construct(private CommercialGovernanceJournal $journal) {}

    public function execute(
        CommercialRecipientDesignationData $designation,
        string $origin,
        string $authorityReference,
        string $sourceReference,
        string $acceptedSnapshotHash,
        ?string $acceptanceEvidenceHash = null,
        ?string $representativeType = null,
        ?string $representativeReference = null,
        ?Model $activatedBy = null,
        ?CarbonImmutable $activatedAt = null,
    ): CommercialRecipientDesignation {
        $activatedAt ??= CarbonImmutable::now();
        $authority = [
            'designation' => $designation->toArray(),
            'origin' => trim($origin),
            'authority_reference' => trim($authorityReference),
            'accepted_snapshot_hash' => strtolower(trim($acceptedSnapshotHash)),
            'acceptance_evidence_hash' => $acceptanceEvidenceHash !== null
                ? strtolower(trim($acceptanceEvidenceHash))
                : null,
            'representative_type' => $representativeType,
            'representative_reference' => $representativeReference,
            'activated_by_type' => $activatedBy?->getMorphClass(),
            'activated_by_id' => $activatedBy?->getKey(),
        ];
        $authorityHash = hash('sha256', json_encode($authority, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        $activated = DB::transaction(function () use (
            $designation,
            $origin,
            $authorityReference,
            $sourceReference,
            $acceptedSnapshotHash,
            $acceptanceEvidenceHash,
            $representativeType,
            $representativeReference,
            $activatedBy,
            $activatedAt,
            $authorityHash,
        ): CommercialRecipientDesignation {
            $existing = CommercialRecipientDesignation::query()
                ->where('designation_reference', $designation->settlementDesignationReference)
                ->orWhere('authority_reference', $authorityReference)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof CommercialRecipientDesignation) {
                if ($existing->authority_hash !== $authorityHash) {
                    throw new DomainException('Commercial Recipient Designation authority conflicts with an existing immutable activation.');
                }

                return $existing;
            }

            return CommercialRecipientDesignation::query()->create([
                'designation_reference' => $designation->settlementDesignationReference,
                'counterparty_reference' => $designation->counterpartyReference,
                'commercial_role' => $designation->commercialRole,
                'component_scope' => $designation->componentScope,
                'agreement_reference' => $designation->agreementReference,
                'settlement_designation_reference' => $designation->settlementDesignationReference,
                'settlement_disposition' => $designation->settlementDisposition->value,
                'settlement_account_reference' => $designation->settlementAccountReference,
                'settlement_principal_reference' => $designation->settlementPrincipalReference,
                'tax_profile_reference' => $designation->taxProfileReference,
                'origin' => trim($origin),
                'authority_reference' => trim($authorityReference),
                'authority_hash' => $authorityHash,
                'source_reference' => trim($sourceReference),
                'representative_type' => $representativeType,
                'representative_reference' => $representativeReference,
                'accepted_snapshot_hash' => strtolower(trim($acceptedSnapshotHash)),
                'acceptance_evidence_hash' => $acceptanceEvidenceHash !== null
                    ? strtolower(trim($acceptanceEvidenceHash))
                    : null,
                'activated_by_type' => $activatedBy?->getMorphClass(),
                'activated_by_id' => $activatedBy?->getKey(),
                'effective_from' => UtcInstant::parseOffsetRequired($designation->effectiveFrom),
                'effective_until' => $designation->effectiveUntil !== null
                    ? UtcInstant::parseOffsetRequired($designation->effectiveUntil)
                    : null,
                'activated_at' => $activatedAt,
            ]);
        }, attempts: 5);
        $this->journal->recordRecipientDesignation($activated, 'commercial.recipient_designation.activated');

        return $activated;
    }
}
