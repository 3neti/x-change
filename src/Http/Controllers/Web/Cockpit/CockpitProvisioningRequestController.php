<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Actions\Provisioning\CreateCockpitProvisioningRequest;
use LBHurtado\XChange\Http\Requests\Web\Cockpit\StoreProvisioningRequest;

final class CockpitProvisioningRequestController extends Controller
{
    public function store(
        StoreProvisioningRequest $request,
        CreateCockpitProvisioningRequest $create,
    ): RedirectResponse {
        $create->handle($request->user(), $request->validated());

        return back()->with('success', 'Provisioning request submitted for independent approval.');
    }
}
