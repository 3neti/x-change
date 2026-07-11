<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\XChange\Services\BuildBalanceOverview;
use LBHurtado\XChange\Services\BuildProvisioningRequirementViewData;

class PayCodeCreatePageController extends Controller
{
    public function __invoke(
        Request $request,
        BuildProvisioningRequirementViewData $provisioning,
        BuildBalanceOverview $balances,
    ): Response {
        return Inertia::render('x-change/pay-codes/Create', [
            'provisioning_requirement' => $provisioning->handle(
                session()->get('xchange.pay_codes.provisioning_requirement')
            ),
            'balance_overview' => $balances->handle($request->user()),
            'cockpit_bridge' => [
                'schema' => 'x-change.pay-code-create.cockpit-bridge.v1',
                'status' => 'available',
                'relationship' => 'legacy-advanced-form-to-cockpit-template-runtime',
                'legacy_owner' => 'PayCodeCreatePageController',
                'cockpit_route' => Route::has('x-change.cockpit.quick-generate')
                    ? route('x-change.cockpit.quick-generate', absolute: false)
                    : null,
                'mutation' => [
                    'legacy_page_remains_owner' => true,
                    'cockpit_replaces_legacy_page' => false,
                    'campaign_mutation_enabled' => false,
                ],
                'redactions' => [
                    'payloads' => 'bridge-metadata-only',
                ],
            ],
        ]);
    }
}
