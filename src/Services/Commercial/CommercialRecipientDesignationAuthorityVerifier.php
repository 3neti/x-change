<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use DomainException;
use LBHurtado\XChange\Models\CommercialRecipientDesignation;
use LBHurtado\XProvisioning\Data\CommercialRecipientDesignationData;
use LBHurtado\XProvisioning\Enums\CommercialSettlementDisposition;
use Throwable;

final readonly class CommercialRecipientDesignationAuthorityVerifier
{
    public function assertValid(CommercialRecipientDesignation $designation): void
    {
        try {
            $authority = [
                'designation' => (new CommercialRecipientDesignationData(
                    counterpartyReference: (string) $designation->counterparty_reference,
                    commercialRole: (string) $designation->commercial_role,
                    componentScope: array_values((array) $designation->component_scope),
                    agreementReference: (string) $designation->agreement_reference,
                    settlementDesignationReference: (string) $designation->settlement_designation_reference,
                    taxProfileReference: filled($designation->tax_profile_reference)
                        ? (string) $designation->tax_profile_reference
                        : null,
                    effectiveFrom: $designation->effective_from->toRfc3339String(),
                    effectiveUntil: $designation->effective_until?->toRfc3339String(),
                    settlementDisposition: CommercialSettlementDisposition::from(
                        (string) $designation->settlement_disposition,
                    ),
                    settlementAccountReference: filled($designation->settlement_account_reference)
                        ? (string) $designation->settlement_account_reference
                        : null,
                    settlementPrincipalReference: filled($designation->settlement_principal_reference)
                        ? (string) $designation->settlement_principal_reference
                        : null,
                ))->toArray(),
                'origin' => trim((string) $designation->origin),
                'authority_reference' => trim((string) $designation->authority_reference),
                'accepted_snapshot_hash' => strtolower(trim((string) $designation->accepted_snapshot_hash)),
                'acceptance_evidence_hash' => filled($designation->acceptance_evidence_hash)
                    ? strtolower(trim((string) $designation->acceptance_evidence_hash))
                    : null,
                'representative_type' => $designation->representative_type,
                'representative_reference' => $designation->representative_reference,
                'activated_by_type' => $designation->activated_by_type,
                'activated_by_id' => $designation->activated_by_id,
            ];
            $hash = hash('sha256', json_encode($authority, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        } catch (Throwable) {
            $this->fail($designation);
        }

        if (! hash_equals((string) $designation->authority_hash, $hash)) {
            $this->fail($designation);
        }
    }

    private function fail(CommercialRecipientDesignation $designation): never
    {
        throw new DomainException(
            "Commercial Recipient Designation [{$designation->designation_reference}] failed immutable authority verification.",
        );
    }
}
