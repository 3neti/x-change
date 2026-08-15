<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Enums\ProvisioningOperatorCapability;
use LBHurtado\XChange\Http\Requests\Web\Cockpit\ActivateProvisioningRequest;
use LBHurtado\XChange\Services\Provisioning\ProvisioningOperatorAuthority;
use LBHurtado\XProvisioning\Actions\ActivateProvisioningAcceptance;
use LBHurtado\XProvisioning\Models\ProvisioningOffer;

final class CockpitProvisioningActivationController extends Controller
{
    public function store(
        ActivateProvisioningRequest $request,
        ProvisioningOffer $provisioningOffer,
        ProvisioningOperatorAuthority $authority,
        ActivateProvisioningAcceptance $activate,
    ): RedirectResponse {
        $authority->assertAllows($request->user(), ProvisioningOperatorCapability::Activate);
        $activate->handle($provisioningOffer, $request->user());

        return back()->with('success', 'The exact accepted authority snapshot is now active.');
    }
}
