<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Funding;

use LBHurtado\XChange\Models\FundingEvidenceClaim;
use LBHurtado\XChange\Models\FundingEvidenceClaimSupersession;
use LogicException;

final readonly class FundingEvidenceClaimOwnership
{
    public function effectiveIntentId(FundingEvidenceClaim $claim, bool $lock = false): int
    {
        $query = FundingEvidenceClaimSupersession::query()
            ->where('funding_evidence_claim_id', $claim->getKey());

        if ($lock) {
            $query->lockForUpdate();
        }

        $supersession = $query->first();

        return (int) ($supersession?->to_funding_intent_id ?? $claim->funding_intent_id);
    }

    public function effectiveClaimForIntent(int $fundingIntentId, bool $lock = false): ?FundingEvidenceClaim
    {
        $directQuery = FundingEvidenceClaim::query()
            ->where('funding_intent_id', $fundingIntentId);
        $supersessionQuery = FundingEvidenceClaimSupersession::query()
            ->where('to_funding_intent_id', $fundingIntentId);

        if ($lock) {
            $directQuery->lockForUpdate();
            $supersessionQuery->lockForUpdate();
        }

        $direct = $directQuery->first();
        $supersession = $supersessionQuery->first();
        $directRemainsEffective = $direct instanceof FundingEvidenceClaim
            && $this->effectiveIntentId($direct, $lock) === $fundingIntentId;

        if ($directRemainsEffective && $supersession instanceof FundingEvidenceClaimSupersession) {
            throw new LogicException('A Funding Intent has conflicting effective evidence claims.');
        }

        if ($directRemainsEffective) {
            return $direct;
        }

        return $supersession?->claim()->first();
    }
}
