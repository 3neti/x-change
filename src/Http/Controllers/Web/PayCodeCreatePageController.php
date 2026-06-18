<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\XChange\Services\BuildProvisioningRequirementViewData;

class PayCodeCreatePageController extends Controller
{
    public function __invoke(Request $request, BuildProvisioningRequirementViewData $provisioning): Response
    {
        return Inertia::render('x-change/pay-codes/Create', [
            'provisioning_requirement' => $provisioning->handle(
                session()->get('xchange.pay_codes.provisioning_requirement')
            ),
        ]);
    }
}
