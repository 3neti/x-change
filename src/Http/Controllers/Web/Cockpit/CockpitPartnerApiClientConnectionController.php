<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Laravel\Passport\Client;
use LBHurtado\XChange\Http\Requests\Web\Cockpit\CheckPartnerApiClientRequest;
use LBHurtado\XChange\Models\PartnerApiClient;

final class CockpitPartnerApiClientConnectionController extends Controller
{
    public function __invoke(
        CheckPartnerApiClientRequest $request,
        PartnerApiClient $partnerApiClient,
    ): JsonResponse {
        $oauthClient = Client::query()->find($partnerApiClient->oauth_client_id);
        $checks = [
            'partner_api_enabled' => (bool) config('x-change.partner_api.enabled', false),
            'client_active' => $partnerApiClient->isActive(),
            'oauth_client_available' => $oauthClient instanceof Client && ! $oauthClient->revoked,
            'issuer_available' => $partnerApiClient->issuer()->exists(),
            'scopes_configured' => $partnerApiClient->scopes !== [],
        ];

        return response()->json([
            'schema' => 'x-change.partner-api-connection-check.v1',
            'ready' => ! in_array(false, $checks, true),
            'checks' => $checks,
            'provider_calls' => false,
            'financial_mutation' => false,
        ], headers: ['Cache-Control' => 'no-store, private']);
    }
}
