<?php

declare(strict_types=1);

it('publishes safe standards-based discovery even before runtime activation', function () {
    config()->set('x-change.partner_api.enabled', false);

    $this->getJson('/.well-known/oauth-authorization-server')
        ->assertSuccessful()
        ->assertJsonPath('grant_types_supported.0', 'client_credentials')
        ->assertJsonPath('token_endpoint', url('/oauth/token'));

    $this->getJson('/.well-known/oauth-protected-resource')
        ->assertSuccessful()
        ->assertJsonPath('bearer_methods_supported.0', 'header');

    $this->getJson('/.well-known/x-change-partner-api')
        ->assertSuccessful()
        ->assertJsonPath('status', 'not_enabled')
        ->assertJsonPath('access.self_service_registration', false)
        ->assertJsonPath('safety.issuer_identity', 'bound_to_oauth_client')
        ->assertJsonMissingPath('credentials');

    $this->get('/llms.txt')
        ->assertSuccessful()
        ->assertHeader('content-type', 'text/plain; charset=UTF-8')
        ->assertSee('Do not use legacy /api/x/v1 lifecycle endpoints.');
});

it('serves a valid curated OpenAPI contract without unsafe lifecycle operations', function () {
    $response = $this->get('/api/partner/openapi.json')->assertSuccessful();
    $document = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);

    expect(data_get($document, 'openapi'))->toBe('3.1.0')
        ->and(data_get($document, 'info.title'))->toBe('X-Change Partner API')
        ->and(data_get($document, 'components.securitySchemes.partnerOAuth.flows.clientCredentials.tokenUrl'))
        ->toBe('/oauth/token')
        ->and(array_keys((array) data_get($document, 'paths')))->toBe([
            '/capabilities',
            '/pay-code-estimates',
            '/pay-codes',
            '/pay-codes/{code}',
            '/pay-codes/{code}/cancellation',
        ])
        ->and($response->getContent())->not->toContain('/api/x/v1')
        ->not->toContain('issuer_id');
});
