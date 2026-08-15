<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Enums\ProvisioningOperatorCapability;
use LBHurtado\XChange\Http\Requests\Web\Cockpit\SupersedeProvisioningRequest;
use LBHurtado\XChange\Services\Provisioning\ProvisioningOperatorAuthority;
use LBHurtado\XProvisioning\Actions\SupersedeProvisioningAcceptance;
use LBHurtado\XProvisioning\Models\ProvisioningOffer;

final class CockpitProvisioningSupersessionController extends Controller
{
    public function store(
        SupersedeProvisioningRequest $request,
        ProvisioningOffer $provisioningOffer,
        ProvisioningOperatorAuthority $authority,
        SupersedeProvisioningAcceptance $supersede,
    ): RedirectResponse {
        $authority->assertAllows($request->user(), ProvisioningOperatorCapability::Revoke);
        $replacement = ProvisioningOffer::query()
            ->where('reference', $request->validated('replacement_offer_reference'))
            ->firstOrFail();
        $supersede->handle(
            $provisioningOffer,
            $replacement,
            $request->user(),
            (string) $request->validated('reason'),
        );

        return back()->with('success', 'The replacement remains active and the predecessor is now superseded.');
    }
}
