<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Crypt;
use LBHurtado\XChange\Http\Requests\Web\Cockpit\DeliverProvisioningOfferRequest;
use LBHurtado\XChange\Jobs\Provisioning\DeliverProvisioningOfferJob;
use LBHurtado\XProvisioning\Models\ProvisioningOffer;

final class CockpitProvisioningOfferDeliveryController extends Controller
{
    public function __invoke(DeliverProvisioningOfferRequest $request, ProvisioningOffer $provisioningOffer): JsonResponse
    {
        $validated = $request->validated();
        abort_unless(
            hash_equals((string) $provisioningOffer->claim_token_hash, hash('sha256', $validated['claim_token'])),
            404,
        );
        DeliverProvisioningOfferJob::dispatch(
            $provisioningOffer->reference,
            $validated['channel'],
            $validated['recipient'],
            Crypt::encryptString($validated['claim_token']),
        )->afterCommit();

        return response()->json([
            'status' => 'queued',
            'queue' => 'x-change-feedback',
            'channel' => $validated['channel'],
        ], 202);
    }
}
