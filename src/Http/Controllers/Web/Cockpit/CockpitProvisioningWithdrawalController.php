<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Enums\ProvisioningOperatorCapability;
use LBHurtado\XChange\Http\Requests\Web\Cockpit\WithdrawProvisioningRequest as WithdrawProvisioningFormRequest;
use LBHurtado\XChange\Services\Provisioning\ProvisioningOperatorAuthority;
use LBHurtado\XProvisioning\Actions\WithdrawProvisioningRequest;
use LBHurtado\XProvisioning\Models\ProvisioningRequest;

final class CockpitProvisioningWithdrawalController extends Controller
{
    public function store(
        WithdrawProvisioningFormRequest $request,
        ProvisioningRequest $provisioningRequest,
        ProvisioningOperatorAuthority $authority,
        WithdrawProvisioningRequest $withdraw,
    ): RedirectResponse {
        $authority->assertAllows($request->user(), ProvisioningOperatorCapability::Request);
        $withdraw->handle(
            $provisioningRequest,
            $request->user(),
            (string) $request->validated('reason'),
        );

        return back()->with('success', 'The provisioning request was withdrawn.');
    }
}
