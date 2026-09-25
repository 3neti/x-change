<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Settlement;

use LBHurtado\XChange\Data\Settlement\AuiDemonstrationPolicyResponseData;
use LBHurtado\XChange\Data\Settlement\PolicyCompletionPreparationData;
use ThreeNeti\SettlementEnvelopeAui\Services\ParseAuiPolicyResponse;

/** Compatibility boundary; the standalone integration owns response validation. */
final class ParseAuiDemonstrationPolicyResponse
{
    public function __construct(private ParseAuiPolicyResponse $parser = new ParseAuiPolicyResponse) {}

    public function handle(mixed $payload, PolicyCompletionPreparationData $preparation): AuiDemonstrationPolicyResponseData
    {
        $result = $this->parser->handle($payload, $preparation->coverageEffectiveAt, $preparation->coverageExpiresAt);

        return new AuiDemonstrationPolicyResponseData(
            $result->policyReference,
            $result->productCode,
            $result->effectiveAt,
            $result->expiresAt,
        );
    }
}
