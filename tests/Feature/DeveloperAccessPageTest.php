<?php

declare(strict_types=1);

it('shows the developer reference to guests without exposing credentials', function (): void {
    config()->set('x-change.partner_api.access_contact', 'developer@example.test');
    config()->set('x-change.partner_api.enabled', true);
    config()->set('x-mcp.enabled', true);
    config()->set('x-mcp.public_issuance.enabled', false);

    $this->withHeaders(['X-Inertia' => 'true', 'Accept' => 'text/html, application/xhtml+xml'])
        ->get(route('x-change.developer-access.show'))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/public/DeveloperAccess')
        ->assertJsonPath('props.access_contact', 'developer@example.test')
        ->assertJsonPath('props.spec_url', '/api/partner/openapi.json')
        ->assertJsonPath('props.partner_enabled', true)
        ->assertJsonPath('props.mcp_enabled', true)
        ->assertJsonMissingPath('props.client_secret')
        ->assertJsonMissingPath('props.access_token');
});

it('redirects the old API documentation page to Developer Access', function (): void {
    $this->get('/docs/api')->assertRedirect('/x/developers');
});
