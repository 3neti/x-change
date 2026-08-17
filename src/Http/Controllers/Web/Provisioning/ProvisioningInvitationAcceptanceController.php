<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Provisioning;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use LBHurtado\XChange\Http\Requests\Web\AcceptProvisioningInvitationRequest;
use LBHurtado\XChange\Services\Provisioning\BuildProvisioningAcceptanceEvidence;
use LBHurtado\XProvisioning\Actions\AcceptProvisioningOffer;
use LBHurtado\XProvisioning\Models\ProvisioningOffer;

final class ProvisioningInvitationAcceptanceController extends Controller
{
    public function __invoke(
        AcceptProvisioningInvitationRequest $request,
        string $token,
        AcceptProvisioningOffer $accept,
        BuildProvisioningAcceptanceEvidence $evidence,
    ): RedirectResponse {
        $candidate = $request->user();
        abort_unless($candidate instanceof Model, 403);

        $candidateAttributes = $candidate->getAttributes();
        $mobileVerified = ! array_key_exists('mobile_verified_at', $candidateAttributes)
            || $candidate->getAttribute('mobile_verified_at') !== null;

        if (! $mobileVerified) {
            throw ValidationException::withMessages([
                'responsibility_attestation' => ['Verify your mobile number before accepting this authority.'],
            ]);
        }

        $offer = ProvisioningOffer::query()
            ->with('revision.request')
            ->where('claim_token_hash', hash('sha256', $token))
            ->firstOrFail();

        $accept->handle(
            claimToken: $token,
            candidateType: $candidate->getMorphClass(),
            candidateReference: (string) $candidate->getKey(),
            evidence: $evidence->build(
                revision: $offer->revision,
                candidate: $candidate,
                responsibilityAttestation: (bool) $request->validated('responsibility_attestation'),
            ),
        );

        return redirect()->route('x-change.provisioning.claim.show', ['token' => $token])
            ->with('success', 'Invitation accepted. The approved authority is awaiting controlled activation.');
    }
}
