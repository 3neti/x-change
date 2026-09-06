<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Responses;

use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\XChange\Data\Claim\ClaimShareMetadataData;
use LBHurtado\XChange\Data\Claim\ClaimSurfaceData;

class ClaimEntryResponseFactory
{
    public function render(
        ?string $initialCode = null,
        ?array $claimExperience = null,
        ?array $provisioningRequirement = null,
        ?ClaimShareMetadataData $shareMetadata = null,
        ?ClaimSurfaceData $claimSurface = null,
    ): Response {
        $response = Inertia::render('x-change/claim/Entry', [
            'initial_code' => $initialCode,
            'claim_experience' => $claimExperience,
            'provisioning_requirement' => $provisioningRequirement,
            'claim_surface' => $claimSurface?->toArray(),
        ])->rootView('x-change::claim-root');

        if ($shareMetadata !== null) {
            $response->withViewData(
                'claimShareMetadata',
                $shareMetadata->toArray(),
            );
        }

        return $response;
    }

    public function error(string $message, string $code): Response
    {
        return Inertia::render('x-change/claim/Error', [
            'message' => $message,
            'code' => $code,
        ])->rootView('x-change::claim-root');
    }

    /**
     * @param  array{amount_paid_minor: int, currency: string, completed_at: ?string}|null  $receiptSummary
     */
    public function paymentHandoff(
        string $code,
        string $paymentUrl,
        bool $isFullyCollected,
        ?array $receiptSummary = null,
    ): Response {
        return Inertia::render('x-change/claim/PaymentHandoff', [
            'code' => $code,
            'payment_url' => $isFullyCollected ? null : $paymentUrl,
            'is_fully_collected' => $isFullyCollected,
            'receipt_summary' => $isFullyCollected ? $receiptSummary : null,
        ])->rootView('x-change::claim-root');
    }
}
