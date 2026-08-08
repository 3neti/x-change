<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use LBHurtado\XChange\Enums\CommercialPartnerRevisionStatus;
use LBHurtado\XChange\Enums\CommercialPartnerStatus;
use LBHurtado\XChange\Models\CommercialPartner;
use LBHurtado\XChange\Models\CommercialPartnerLegacyMapping;
use LBHurtado\XChange\Models\CommercialPartnerRevision;
use LBHurtado\XCommerce\Data\CommercialSaleSnapshotData;

final class CommercialPartnerAttributionResolver
{
    /**
     * @return array<string, array{
     *     status:string,
     *     partner_reference:string,
     *     commercial_partner_id:int|null,
     *     commercial_partner_revision_id:int|null,
     *     revision_version:int|null,
     *     attribution_basis:string|null,
     *     authorization_reference:string|null
     * }>
     */
    public function forSnapshot(CommercialSaleSnapshotData $snapshot): array
    {
        $resolved = [];

        foreach ($snapshot->quoteSnapshot->allocationPlan->lines as $line) {
            if ($line->category !== 'partner_commission') {
                continue;
            }

            $resolved[$line->policyRuleReference] = $this->resolve($line->recipientReference);
        }

        ksort($resolved);

        return $resolved;
    }

    /**
     * @return array{
     *     status:string,
     *     partner_reference:string,
     *     commercial_partner_id:int|null,
     *     commercial_partner_revision_id:int|null,
     *     revision_version:int|null,
     *     attribution_basis:string|null,
     *     authorization_reference:string|null
     * }
     */
    public function resolve(string $partnerReference): array
    {
        $partner = CommercialPartner::query()
            ->where('reference', $partnerReference)
            ->where('status', CommercialPartnerStatus::Active)
            ->first();

        $revision = $partner?->revisions()
            ->where('status', CommercialPartnerRevisionStatus::Approved)
            ->first();

        if ($partner instanceof CommercialPartner && $revision instanceof CommercialPartnerRevision) {
            return $this->result('governed', $partnerReference, $partner, $revision);
        }

        $mapping = CommercialPartnerLegacyMapping::query()
            ->with(['partner', 'partnerRevision'])
            ->where('legacy_partner_reference', $partnerReference)
            ->first();

        if ($mapping instanceof CommercialPartnerLegacyMapping
            && $mapping->partner->status === CommercialPartnerStatus::Active
            && $mapping->partnerRevision->status === CommercialPartnerRevisionStatus::Approved) {
            return $this->result('legacy_mapped', $partnerReference, $mapping->partner, $mapping->partnerRevision);
        }

        return [
            'status' => 'legacy_unresolved',
            'partner_reference' => $partnerReference,
            'commercial_partner_id' => null,
            'commercial_partner_revision_id' => null,
            'revision_version' => null,
            'attribution_basis' => null,
            'authorization_reference' => null,
        ];
    }

    /**
     * @return array{
     *     status:string,
     *     partner_reference:string,
     *     commercial_partner_id:int,
     *     commercial_partner_revision_id:int,
     *     revision_version:int,
     *     attribution_basis:string,
     *     authorization_reference:string
     * }
     */
    private function result(
        string $status,
        string $partnerReference,
        CommercialPartner $partner,
        CommercialPartnerRevision $revision,
    ): array {
        return [
            'status' => $status,
            'partner_reference' => $partnerReference,
            'commercial_partner_id' => (int) $partner->getKey(),
            'commercial_partner_revision_id' => (int) $revision->getKey(),
            'revision_version' => $revision->version,
            'attribution_basis' => $revision->attribution_basis,
            'authorization_reference' => $revision->authorization_reference,
        ];
    }
}
