<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Enums\ProvisioningOperatorCapability;
use LBHurtado\XChange\Http\Requests\Web\Cockpit\RevokeProvisioningRequest;
use LBHurtado\XChange\Services\Provisioning\ProvisioningOperatorAuthority;
use LBHurtado\XProvisioning\Actions\RevokeProvisioningAcceptance;
use LBHurtado\XProvisioning\Models\ProvisioningOffer;

final class CockpitProvisioningRevocationController extends Controller
{
    public function store(
        RevokeProvisioningRequest $request,
        ProvisioningOffer $provisioningOffer,
        ProvisioningOperatorAuthority $authority,
        RevokeProvisioningAcceptance $revoke,
    ): RedirectResponse {
        $authority->assertAllows($request->user(), ProvisioningOperatorCapability::Revoke);
        $revoke->handle($provisioningOffer, $request->user(), (string) $request->validated('reason'));

        return back()->with('success', 'The projected authority was revoked without a provider call or money movement.');
    }
}
