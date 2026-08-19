<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use LBHurtado\XChange\Actions\PartnerApi\CreatePartnerApiClient;
use LBHurtado\XChange\Models\PartnerApiClient;
use LBHurtado\XChange\Tests\Fakes\User;

it('returns only the authenticated client mandate and safe identity', function () {
    $issuer = User::query()->create([
        'name' => 'Saras Issuer',
        'email' => 'saras-capabilities@example.test',
        'password' => Hash::make('password'),
    ]);
    $credential = app(CreatePartnerApiClient::class)->handle(
        name: 'Saras AI Sandbox',
        issuer: $issuer,
        scopes: ['capabilities:read', 'pay-codes:issue'],
        mandate: ['maximum_amount_minor' => 50000],
    );
    $oauthClient = Client::query()->findOrFail($credential->client_id);

    Passport::actingAsClient($oauthClient, ['capabilities:read']);

    $this->getJson('/api/partner/v1/capabilities')
        ->assertSuccessful()
        ->assertJsonPath('data.schema', 'x-change.partner-capabilities.v1')
        ->assertJsonPath('data.contract.version', '1.2.0')
        ->assertJson(fn ($json) => $json->whereType('data.contract.sha256', 'string')->etc())
        ->assertJsonPath('data.client.reference', $credential->reference)
        ->assertJsonPath('data.client.name', 'Saras AI Sandbox')
        ->assertJsonPath('data.constraints.maximum_amount_minor', 50000)
        ->assertJsonMissingPath('data.client.oauth_client_id')
        ->assertJsonMissingPath('data.client.issuer_id')
        ->assertJsonMissingPath('data.client.client_secret');
});

it('rejects missing scopes and inactive partner clients', function () {
    $issuer = User::query()->create([
        'name' => 'Saras Issuer',
        'email' => 'saras-inactive@example.test',
        'password' => Hash::make('password'),
    ]);
    $credential = app(CreatePartnerApiClient::class)->handle(
        name: 'Saras AI Sandbox',
        issuer: $issuer,
        scopes: ['capabilities:read'],
    );
    $oauthClient = Client::query()->findOrFail($credential->client_id);

    Passport::actingAsClient($oauthClient, []);
    $this->getJson('/api/partner/v1/capabilities')->assertForbidden();

    PartnerApiClient::query()->where('reference', $credential->reference)->update([
        'status' => 'suspended',
        'suspended_at' => now(),
    ]);
    Passport::actingAsClient($oauthClient, ['capabilities:read']);
    $this->getJson('/api/partner/v1/capabilities')->assertUnauthorized();
});
