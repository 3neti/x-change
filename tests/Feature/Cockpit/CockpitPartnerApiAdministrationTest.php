<?php

declare(strict_types=1);

use Laravel\Passport\Client;
use Laravel\Passport\Token;
use LBHurtado\XChange\Actions\PartnerApi\CreatePartnerApiClient;
use LBHurtado\XChange\Enums\PartnerApiClientStatus;
use LBHurtado\XChange\Enums\PartnerApiOperatorCapability;
use LBHurtado\XChange\Models\PartnerApiClient;
use LBHurtado\XChange\Models\PartnerApiOperatorAuthorization;
use LBHurtado\XChange\Tests\Fakes\User;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

function authorizePartnerApiOperator(User $operator, PartnerApiOperatorCapability ...$capabilities): void
{
    foreach ($capabilities as $capability) {
        PartnerApiOperatorAuthorization::query()->create([
            'operator_type' => $operator->getMorphClass(),
            'operator_id' => $operator->getKey(),
            'capability' => $capability->value,
            'authorization_reference' => 'partner-api-test:'.$capability->value,
            'valid_from' => now()->subMinute(),
        ]);
    }
}

it('conceals API Partner administration from ordinary Account holders', function (): void {
    actingAsTestUser();

    $this->get(route('x-change.cockpit.api-partners.index'))->assertNotFound();
});

it('grants Partner API authority to a named human operator through commissioning', function (): void {
    $system = actingAsTestUser();
    config()->set('account.system_user.candidates', [
        'x-change' => [
            'model' => User::class,
            'identifier' => $system->email,
            'identifier_column' => 'email',
        ],
    ]);
    $operator = User::query()->create([
        'name' => 'API Operator',
        'email' => 'api-operator@example.test',
        'password' => 'password',
    ]);

    $this->artisan('x-change:partner-api:authorize-operator', [
        'operator' => $operator->email,
        '--column' => 'email',
        '--capability' => [PartnerApiOperatorCapability::ViewClients->value],
        '--authorization-reference' => 'board-resolution:partner-api-test',
    ])->assertSuccessful();

    expect(PartnerApiOperatorAuthorization::query()->sole()->capability)
        ->toBe(PartnerApiOperatorCapability::ViewClients->value)
        ->and(ExecutionJournalEntry::query()
            ->where('event_type', 'partner_api.operator.authorized')->count())->toBe(1);
});

it('shows API Partner administration and navigation to an authorized operator', function (): void {
    $operator = actingAsTestUser();
    authorizePartnerApiOperator($operator, PartnerApiOperatorCapability::ViewClients);

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.api-partners.index'))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/cockpit/ApiPartners')
        ->assertJsonPath('props.partner_api.schema', 'x-change.cockpit.partner-api-clients.v1')
        ->assertJsonPath('props.partner_api.production_governance', 'maker_checker_required')
        ->assertJsonPath('props.partner_api.can_create_sandbox', false)
        ->assertJsonPath('props.xchange.navigation.api_partner_controls_visible', true)
        ->assertJsonMissing(['client_secret']);
});

it('provisions a bounded sandbox credential and displays its secret exactly once', function (): void {
    $operator = actingAsTestUser();
    authorizePartnerApiOperator(
        $operator,
        PartnerApiOperatorCapability::ViewClients,
        PartnerApiOperatorCapability::ManageSandboxClients,
    );

    $response = $this->postJson(route('x-change.cockpit.api-partners.clients.store'), [
        'name' => 'Saras Sandbox',
        'environment' => 'sandbox',
        'issuer_id' => (string) $operator->getKey(),
        'scopes' => ['capabilities:read', 'pay-codes:estimate', 'pay-codes:read'],
        'currencies' => ['PHP'],
        'settlement_rails' => ['automatic', 'INSTAPAY'],
        'maximum_amount_minor' => 50000,
        'daily_principal_limit_minor' => 200000,
        'unbound_pay_codes' => false,
        'acknowledge_secret_once' => true,
    ]);

    $response->assertCreated()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('schema', 'x-change.partner-api-credential.v1')
        ->assertJsonPath('secret_display', 'one_time_only')
        ->assertJsonPath('mandate.maximum_amount_minor', 50000);

    $secret = (string) $response->json('client_secret');
    expect($secret)->not->toBeEmpty()
        ->and(PartnerApiClient::query()->sole()->environment)->toBe('sandbox')
        ->and(Client::query()->sole()->secret)->not->toBe($secret)
        ->and(ExecutionJournalEntry::query()
            ->where('event_type', 'partner_api.client.activated')->count())->toBe(1)
        ->and(ExecutionJournalEntry::query()->get()->toJson())->not->toContain($secret);

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.api-partners.index'))
        ->assertOk()
        ->assertJsonMissing([$secret])
        ->assertJsonMissing(['client_secret']);
});

it('rejects production provisioning even with the legacy confirmation switch', function (): void {
    $issuer = actingAsTestUser();

    $this->artisan('x-change:partner-api:client', [
        'name' => 'Production Client',
        '--issuer' => $issuer->email,
        '--environment' => 'production',
        '--confirm-production' => true,
    ])->assertFailed();

    expect(PartnerApiClient::query()->count())->toBe(0)
        ->and(Client::query()->count())->toBe(0);
});

it('suspends and terminally revokes clients while invalidating Passport access', function (): void {
    $operator = actingAsTestUser();
    authorizePartnerApiOperator(
        $operator,
        PartnerApiOperatorCapability::ManageSandboxClients,
        PartnerApiOperatorCapability::SuspendClients,
        PartnerApiOperatorCapability::RevokeClients,
    );

    $credential = app(CreatePartnerApiClient::class)->handle(
        name: 'Saras Lifecycle', issuer: $operator,
    );
    $client = PartnerApiClient::query()->sole();
    Token::query()->create([
        'id' => 'partner-api-access-token',
        'client_id' => $credential->client_id,
        'scopes' => [],
        'revoked' => false,
        'expires_at' => now()->addHour(),
    ]);

    $this->post(route('x-change.cockpit.api-partners.clients.suspensions.store', $client))
        ->assertRedirect();

    expect($client->refresh()->status)->toBe(PartnerApiClientStatus::Suspended)
        ->and(Token::query()->sole()->revoked)->toBeTrue()
        ->and(Client::query()->findOrFail($credential->client_id)->revoked)->toBeFalse();

    $this->post(route('x-change.cockpit.api-partners.clients.revocations.store', $client))
        ->assertRedirect();

    expect($client->refresh()->status)->toBe(PartnerApiClientStatus::Revoked)
        ->and(Client::query()->findOrFail($credential->client_id)->revoked)->toBeTrue()
        ->and(ExecutionJournalEntry::query()
            ->whereIn('event_type', ['partner_api.client.suspended', 'partner_api.client.revoked'])
            ->count())->toBe(2);
});

it('checks client readiness without a provider call or financial mutation', function (): void {
    $operator = actingAsTestUser();
    authorizePartnerApiOperator($operator, PartnerApiOperatorCapability::ViewClients);
    $credential = app(CreatePartnerApiClient::class)->handle(
        name: 'Saras Readiness', issuer: $operator,
    );
    $client = PartnerApiClient::query()->sole();

    $this->postJson(route('x-change.cockpit.api-partners.clients.checks.store', $client))
        ->assertOk()
        ->assertJsonPath('ready', true)
        ->assertJsonPath('checks.oauth_client_available', true)
        ->assertJsonPath('provider_calls', false)
        ->assertJsonPath('financial_mutation', false);

    expect(PartnerApiClient::query()->sole()->oauth_client_id)->toBe($credential->client_id);
});
