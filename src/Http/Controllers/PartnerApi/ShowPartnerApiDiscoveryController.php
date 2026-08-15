<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\PartnerApi;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Route;

class ShowPartnerApiDiscoveryController extends Controller
{
    public function authorizationServer(): JsonResponse
    {
        return response()->json([
            'issuer' => url('/'),
            'token_endpoint' => $this->tokenEndpoint(),
            'grant_types_supported' => ['client_credentials'],
            'token_endpoint_auth_methods_supported' => ['client_secret_basic', 'client_secret_post'],
            'scopes_supported' => array_keys((array) config('x-change.partner_api.scopes', [])),
        ]);
    }

    public function protectedResource(): JsonResponse
    {
        return response()->json([
            'resource' => url('/'.trim((string) config('x-change.partner_api.prefix'), '/')),
            'authorization_servers' => [url('/')],
            'bearer_methods_supported' => ['header'],
            'scopes_supported' => array_keys((array) config('x-change.partner_api.scopes', [])),
        ]);
    }

    public function partnerApi(): JsonResponse
    {
        return response()->json([
            'schema' => 'x-change.partner-api-discovery.v1',
            'name' => 'X-Change Partner API',
            'status' => (bool) config('x-change.partner_api.enabled') ? 'available' : 'not_enabled',
            'audience' => 'Approved server-to-server partners and AI services.',
            'authentication' => [
                'type' => 'OAuth 2.0 client credentials',
                'token_endpoint' => $this->tokenEndpoint(),
                'authorization_server_metadata' => route('x-change.partner-api.discovery.authorization-server'),
                'protected_resource_metadata' => route('x-change.partner-api.discovery.protected-resource'),
            ],
            'documentation' => [
                'openapi' => route('x-change.partner-api.discovery.openapi'),
                'human_guide' => config('x-change.partner_api.documentation_url'),
            ],
            'access' => [
                'self_service_registration' => false,
                'contact' => config('x-change.partner_api.access_contact'),
                'note' => 'Credentials are provisioned by an authorized operator and are shown once.',
            ],
            'safety' => [
                'issuer_identity' => 'bound_to_oauth_client',
                'funding' => 'existing_account_and_treasury_controls',
                'mandate' => 'server_enforced',
                'idempotency' => 'required_for_issuance',
                'legacy_lifecycle_api' => 'not_for_partner_use',
            ],
        ]);
    }

    public function llms(): Response
    {
        $lines = [
            '# X-Change Partner API',
            '',
            '> A governed server-to-server API for approved partners and AI services.',
            '',
            '- Discovery: '.route('x-change.partner-api.discovery.index'),
            '- OpenAPI: '.route('x-change.partner-api.discovery.openapi'),
            '- OAuth metadata: '.route('x-change.partner-api.discovery.authorization-server'),
            '- Protected resource metadata: '.route('x-change.partner-api.discovery.protected-resource'),
            '- Do not use legacy /api/x/v1 lifecycle endpoints.',
            '- Obtain credentials through the operator-managed access process.',
            '- Issuance always remains subject to OAuth scopes, Account funds, Treasury controls, and the server-side mandate.',
        ];

        return response(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    protected function tokenEndpoint(): string
    {
        return Route::has('passport.token')
            ? route('passport.token')
            : url('/oauth/token');
    }
}
