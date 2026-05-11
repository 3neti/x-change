<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Support\Claim;

use LBHurtado\XChange\Support\Claim\Synchronizers\ApprovedKycContactSynchronizer;

class ClaimEvidenceSynchronizer
{
    public function __construct(
        protected ApprovedKycContactSynchronizer $approvedKycContactSynchronizer,
    ) {}

    public function sync(array $payload): void
    {
        $this->approvedKycContactSynchronizer->sync($payload);
    }
}
