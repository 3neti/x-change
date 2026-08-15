<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Enums\ProvisioningOperatorCapability;
use LBHurtado\XChange\Http\Requests\Web\Cockpit\RejectProvisioningRequest as RejectProvisioningFormRequest;
use LBHurtado\XChange\Services\Provisioning\ProvisioningOperatorAuthority;
use LBHurtado\XProvisioning\Actions\RejectProvisioningRequest;
use LBHurtado\XProvisioning\Models\ProvisioningRequest;

final class CockpitProvisioningRejectionController extends Controller
{
    public function store(
        RejectProvisioningFormRequest $request,
        ProvisioningRequest $provisioningRequest,
        ProvisioningOperatorAuthority $authority,
        RejectProvisioningRequest $reject,
    ): RedirectResponse {
        $authority->assertAllows($request->user(), ProvisioningOperatorCapability::Approve);
        $reject->handle(
            $provisioningRequest,
            $request->user(),
            (string) $request->validated('reason'),
        );

        return back()->with('success', 'The provisioning request was rejected with recorded evidence.');
    }
}
