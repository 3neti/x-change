<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web;

use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

final class DeveloperAccessPageController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('x-change/public/DeveloperAccess', [
            'access_contact' => config('x-change.partner_api.access_contact'),
            'spec_url' => route('x-change.partner-api.discovery.openapi', [], false),
            'partner_enabled' => (bool) config('x-change.partner_api.enabled', false),
            'mcp_enabled' => (bool) config('x-mcp.enabled', false),
            'public_mcp_enabled' => (bool) config('x-mcp.public_issuance.enabled', false),
            'partner_prefix' => '/'.trim((string) config('x-change.partner_api.prefix', 'api/partner/v1'), '/'),
            'mcp_endpoint' => (string) config('x-mcp.endpoint', '/mcp/x-change'),
            'public_mcp_endpoint' => (string) config('x-mcp.public_issuance.endpoint', '/mcp/x-change/public'),
        ])->rootView('x-change::claim-root');
    }
}
