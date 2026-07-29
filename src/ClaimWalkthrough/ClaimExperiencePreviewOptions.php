<?php

declare(strict_types=1);

namespace LBHurtado\XChange\ClaimWalkthrough;

use Illuminate\Contracts\Auth\Authenticatable;

final class ClaimExperiencePreviewOptions
{
    public function __construct(
        public readonly ?Authenticatable $issuer = null,
        public readonly ?string $baseUrl = null,
        public readonly string $profile = 'issuer',
        public readonly bool $dryRun = false,
        public readonly bool $refresh = false,
        public readonly bool $headed = false,
        public readonly int $slowMotion = 100,
        public readonly string $mobile = '09173011987',
        public readonly string $bankCode = 'GXCHPHM2XXX',
        public readonly string $accountNumber = '09173011987',
        public readonly bool $submitClaim = false,
    ) {}
}
