<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Settlement;

use LBHurtado\XChange\Models\CompletionClaimEvidenceProjection;

final readonly class CompletionClaimEvidenceProjectionData
{
    public function __construct(public CompletionClaimEvidenceProjection $projection, public bool $created) {}
}
