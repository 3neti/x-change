<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Responses;

use Inertia\Inertia;
use Inertia\Response;

class ClaimEntryResponseFactory
{
    public function render(
        ?string $initialCode = null,
        ?array $claimExperience = null,
        ?array $provisioningRequirement = null,
    ): Response {
        return Inertia::render('x-change/claim/Entry', [
            'initial_code' => $initialCode,
            'claim_experience' => $claimExperience,
            'provisioning_requirement' => $provisioningRequirement,
        ]);
    }

    public function error(string $message, string $code): Response
    {
        return Inertia::render('x-change/claim/Error', [
            'message' => $message,
            'code' => $code,
        ]);
    }
}
