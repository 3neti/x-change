<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Enums\ProvisioningOperatorCapability;
use LBHurtado\XChange\Http\Requests\Web\Cockpit\IssueProvisioningOfferRequest;
use LBHurtado\XChange\Services\Provisioning\ProvisioningOperatorAuthority;
use LBHurtado\XProvisioning\Actions\IssueProvisioningOffer;
use LBHurtado\XProvisioning\Models\ProvisioningRequest;

final class CockpitProvisioningOfferController extends Controller
{
    public function store(
        IssueProvisioningOfferRequest $request,
        ProvisioningRequest $provisioningRequest,
        ProvisioningOperatorAuthority $authority,
        IssueProvisioningOffer $issue,
    ): JsonResponse {
        $authority->assertAllows($request->user(), ProvisioningOperatorCapability::Issue);
        $credential = $issue->handle($provisioningRequest);

        return response()->json([
            'schema' => 'x-change.provisioning-offer-credential.v1',
            'request_reference' => $provisioningRequest->reference,
            'offer_reference' => $credential->offer->reference,
            'claim_url' => route('x-change.provisioning.claim.show', ['token' => $credential->claimToken]),
            'delivery_url' => route('x-change.cockpit.provisioning.offers.deliveries.store', $credential->offer),
            'expires_at' => $credential->offer->expires_at?->toIso8601String(),
            'secret_display' => 'one_time_only',
        ], 201, [
            'Cache-Control' => 'no-store, private',
            'Pragma' => 'no-cache',
        ]);
    }
}
