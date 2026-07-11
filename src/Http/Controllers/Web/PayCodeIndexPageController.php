<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class PayCodeIndexPageController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('x-change/pay-codes/Index', [
            'cockpit_bridge' => [
                'schema' => 'x-change.pay-code-index.cockpit-bridge.v1',
                'status' => 'available',
                'relationship' => 'legacy-pay-code-list-to-cockpit-explorer',
                'legacy_owner' => 'PayCodeIndexPageController',
                'cockpit_route' => Route::has('x-change.cockpit.pay-codes.index')
                    ? route('x-change.cockpit.pay-codes.index', absolute: false)
                    : null,
                'mutation' => [
                    'legacy_page_remains_owner' => true,
                    'cockpit_replaces_legacy_page' => false,
                ],
                'redactions' => [
                    'payloads' => 'bridge-metadata-only',
                ],
            ],
        ]);
    }
}
