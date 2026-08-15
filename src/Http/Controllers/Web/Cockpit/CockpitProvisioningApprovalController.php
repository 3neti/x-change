<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Enums\ProvisioningOperatorCapability;
use LBHurtado\XChange\Http\Requests\Web\Cockpit\ApproveProvisioningRequest as ApproveProvisioningFormRequest;
use LBHurtado\XChange\Services\Provisioning\ProvisioningOperatorAuthority;
use LBHurtado\XProvisioning\Actions\ApproveProvisioningRequest;
use LBHurtado\XProvisioning\Models\ProvisioningRequest;

final class CockpitProvisioningApprovalController extends Controller
{
    public function store(
        ApproveProvisioningFormRequest $request,
        ProvisioningRequest $provisioningRequest,
        ProvisioningOperatorAuthority $authority,
        ApproveProvisioningRequest $approve,
    ): RedirectResponse {
        $authority->assertAllows($request->user(), ProvisioningOperatorCapability::Approve);
        $approve->handle($provisioningRequest, $request->user());

        return back()->with('success', 'The immutable provisioning snapshot was approved.');
    }
}
