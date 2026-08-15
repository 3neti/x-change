<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Provisioning;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use LBHurtado\ModelChannel\Contracts\HasMobileChannel;
use LBHurtado\XChange\Http\Requests\Web\AcceptProvisioningInvitationRequest;
use LBHurtado\XProvisioning\Actions\AcceptProvisioningOffer;

final class ProvisioningInvitationAcceptanceController extends Controller
{
    public function __invoke(
        AcceptProvisioningInvitationRequest $request,
        string $token,
        AcceptProvisioningOffer $accept,
    ): RedirectResponse {
        $candidate = $request->user();
        abort_unless($candidate instanceof Model, 403);

        $candidateAttributes = $candidate->getAttributes();
        $mobileVerified = ! array_key_exists('mobile_verified_at', $candidateAttributes)
            || $candidate->getAttribute('mobile_verified_at') !== null;
        $mobile = $candidate instanceof HasMobileChannel
            ? $candidate->getMobileChannel()
            : $candidate->getAttribute('mobile');

        if (! $mobileVerified) {
            throw ValidationException::withMessages([
                'responsibility_attestation' => ['Verify your mobile number before accepting this authority.'],
            ]);
        }

        $accept->handle(
            claimToken: $token,
            candidateType: $candidate->getMorphClass(),
            candidateReference: (string) $candidate->getKey(),
            evidence: [
                'name' => (string) $candidate->getAttribute('name'),
                'email' => (string) $candidate->getAttribute('email'),
                'mobile' => (string) $mobile,
                'otp' => $mobileVerified,
                'responsibility_attestation' => (bool) $request->validated('responsibility_attestation'),
            ],
        );

        return redirect()->route('x-change.provisioning.claim.show', ['token' => $token])
            ->with('success', 'Invitation accepted. The approved authority is awaiting controlled activation.');
    }
}
