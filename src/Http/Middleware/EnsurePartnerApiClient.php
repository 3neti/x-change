<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Http\Middleware\EnsureClientIsResourceOwner;
use LBHurtado\XChange\Models\PartnerApiClient;
use LBHurtado\XChange\Services\PartnerApi\PartnerApiRequestContext;
use Symfony\Component\HttpFoundation\Response;

class EnsurePartnerApiClient extends EnsureClientIsResourceOwner
{
    public function handle(Request $request, Closure $next, string ...$scopes): Response
    {
        $token = $this->validateToken($request);
        $this->validate($token, ...$scopes);

        if (! $token instanceof AccessToken) {
            throw new AuthenticationException('A Partner API access token is required.');
        }

        $client = PartnerApiClient::query()
            ->with('issuer')
            ->where('oauth_client_id', (string) $token->oauth_client_id)
            ->first();

        if (! $client?->isActive()) {
            throw new AuthenticationException('The Partner API client is inactive.');
        }

        if (! collect($scopes)->every(fn (string $scope): bool => in_array($scope, $client->scopes, true))) {
            throw new AuthenticationException('The Partner API mandate does not allow this operation.');
        }

        app(PartnerApiRequestContext::class)->setClient($client);

        return $next($request);
    }
}
