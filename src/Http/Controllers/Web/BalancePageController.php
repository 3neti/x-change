<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\XChange\Services\BuildBalanceOverview;
use LBHurtado\XChange\Services\StoredValue\HolderStoredValueReadModel;

class BalancePageController extends Controller
{
    public function __invoke(
        Request $request,
        BuildBalanceOverview $balances,
        HolderStoredValueReadModel $storedValue,
    ): Response {
        $owner = $request->user();

        return Inertia::render('x-change/balances/Index', [
            'balance_overview' => $balances->handle($owner),
            'reusable_balances' => $owner instanceof Model
                ? $storedValue->summaries($owner)
                : [],
            'cockpit_bridge' => [
                'schema' => 'x-change.balances.cockpit-bridge.v1',
                'status' => 'available',
                'relationship' => 'legacy-balance-authority-to-cockpit-funding-preflight',
                'legacy_owner' => 'BalancePageController',
                'cockpit_route' => Route::has('x-change.cockpit.dashboard')
                    ? route('x-change.cockpit.dashboard', absolute: false)
                    : null,
                'mutation' => [
                    'legacy_page_remains_owner' => true,
                    'cockpit_replaces_legacy_page' => false,
                    'funding_mutation_enabled' => false,
                ],
                'redactions' => [
                    'payloads' => 'bridge-metadata-only',
                ],
            ],
        ]);
    }
}
