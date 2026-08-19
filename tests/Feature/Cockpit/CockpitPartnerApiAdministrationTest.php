<?php

declare(strict_types=1);

use Laravel\Passport\Client;
use Laravel\Passport\Token;
use LBHurtado\XChange\Actions\PartnerApi\CreatePartnerApiClient;
use LBHurtado\XChange\Contracts\WalletAccessContract;
use LBHurtado\XChange\Enums\PartnerApiClientStatus;
use LBHurtado\XChange\Enums\PartnerApiOperatorCapability;
use LBHurtado\XChange\Models\PartnerApiClient;
use LBHurtado\XChange\Models\PartnerApiOperatorAuthorization;
use LBHurtado\XChange\Models\PartnerApiProductionMandate;
use LBHurtado\XChange\Tests\Fakes\User;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

beforeEach(function (): void {
    $system = User::query()->create([
        'name' => 'Non-Interactive System Principal',
        'email' => 'partner-api-system@example.test',
        'password' => 'password',
    ]);
    config()->set('account.system_user.candidates', [
        'x-change' => [
            'model' => User::class,
            'identifier' => $system->email,
            'identifier_column' => 'email',
        ],
    ]);
});

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

function allowPartnerApiIssuerWallet(): void
{
    $wallets = Mockery::mock(WalletAccessContract::class);
    $wallets->shouldReceive('resolveForUser')->andReturn((object) ['slug' => 'platform']);
    app()->instance(WalletAccessContract::class, $wallets);
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
    allowPartnerApiIssuerWallet();
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

it('provisions a reusable balance merchant with an explicit bounded mandate', function (): void {
    $operator = actingAsTestUser();
    allowPartnerApiIssuerWallet();
    authorizePartnerApiOperator(
        $operator,
        PartnerApiOperatorCapability::ViewClients,
        PartnerApiOperatorCapability::ManageSandboxClients,
    );

    $response = $this->postJson(route('x-change.cockpit.api-partners.clients.store'), [
        'name' => 'Transit Merchant Sandbox',
        'environment' => 'sandbox',
        'issuer_id' => (string) $operator->getKey(),
        'scopes' => ['stored-value:read', 'stored-value:spend'],
        'currencies' => ['PHP'],
        'settlement_rails' => ['automatic'],
        'maximum_amount_minor' => 5000,
        'daily_principal_limit_minor' => 20000,
        'voucher_profiles' => ['disbursement'],
        'stored_value_spend' => [
            'enabled' => true,
            'currencies' => ['PHP'],
            'maximum_amount_minor' => 500,
            'daily_amount_minor' => 2000,
        ],
        'unbound_pay_codes' => false,
        'acknowledge_secret_once' => true,
    ]);

    $response->assertCreated()
        ->assertJsonPath('mandate.stored_value_spend.enabled', true)
        ->assertJsonPath('mandate.stored_value_spend.maximum_amount_minor', 500)
        ->assertJsonPath('mandate.stored_value_spend.daily_amount_minor', 2000);

    expect(data_get(PartnerApiClient::query()->sole()->mandate, 'stored_value_spend'))
        ->toMatchArray([
            'enabled' => true,
            'currencies' => ['PHP'],
            'maximum_amount_minor' => 500,
            'daily_amount_minor' => 2000,
        ]);

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.api-partners.index'))
        ->assertOk()
        ->assertJsonPath('props.partner_api.clients.0.mandate.stored_value_spend.enabled', true)
        ->assertJsonPath('props.partner_api.clients.0.mandate.stored_value_spend.maximum_amount', '₱5')
        ->assertJsonMissing(['client_secret']);
});

it('rejects stored value scopes without an explicit bounded mandate', function (): void {
    $operator = actingAsTestUser();
    authorizePartnerApiOperator($operator, PartnerApiOperatorCapability::ManageSandboxClients);

    $this->postJson(route('x-change.cockpit.api-partners.clients.store'), [
        'name' => 'Unbounded Transit Merchant',
        'environment' => 'sandbox',
        'issuer_id' => (string) $operator->getKey(),
        'scopes' => ['stored-value:spend'],
        'currencies' => ['PHP'],
        'settlement_rails' => ['automatic'],
        'maximum_amount_minor' => 5000,
        'daily_principal_limit_minor' => 20000,
        'unbound_pay_codes' => false,
        'acknowledge_secret_once' => true,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors([
            'stored_value_spend.enabled',
            'stored_value_spend.currencies',
            'stored_value_spend.maximum_amount_minor',
            'stored_value_spend.daily_amount_minor',
        ]);

    expect(PartnerApiClient::query()->count())->toBe(0)
        ->and(Client::query()->count())->toBe(0);
});

it('provisions the same bounded reusable balance mandate through the commissioning command', function (): void {
    $issuer = actingAsTestUser();

    $this->artisan('x-change:partner-api:client', [
        'name' => 'Transit Merchant CLI',
        '--issuer' => $issuer->email,
        '--scope' => ['stored-value:read', 'stored-value:spend'],
        '--currency' => ['PHP'],
        '--rail' => ['automatic'],
        '--maximum-amount-minor' => 5000,
        '--daily-principal-minor' => 20000,
        '--voucher-profile' => ['disbursement'],
        '--stored-value-spend' => true,
        '--stored-value-maximum-amount-minor' => 500,
        '--stored-value-daily-amount-minor' => 2000,
    ])->assertSuccessful();

    expect(data_get(PartnerApiClient::query()->sole()->mandate, 'stored_value_spend'))
        ->toMatchArray([
            'enabled' => true,
            'currencies' => ['PHP'],
            'maximum_amount_minor' => 500,
            'daily_amount_minor' => 2000,
        ]);
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

it('creates no production credential before independent approval and reveals the secret once at activation', function (): void {
    $maker = actingAsTestUser();
    allowPartnerApiIssuerWallet();
    $checker = User::query()->create([
        'name' => 'Independent API Checker',
        'email' => 'api-checker@example.test',
        'password' => 'password',
    ]);
    authorizePartnerApiOperator(
        $maker,
        PartnerApiOperatorCapability::ViewClients,
        PartnerApiOperatorCapability::RequestProductionClients,
    );
    authorizePartnerApiOperator(
        $checker,
        PartnerApiOperatorCapability::ViewClients,
        PartnerApiOperatorCapability::ApproveProductionClients,
        PartnerApiOperatorCapability::ActivateProductionClients,
    );

    $payload = [
        'name' => 'Saras Production',
        'issuer_id' => (string) $maker->getKey(),
        'scopes' => ['capabilities:read', 'pay-codes:issue', 'pay-codes:read'],
        'currencies' => ['PHP'],
        'settlement_rails' => ['automatic', 'INSTAPAY'],
        'maximum_amount_minor' => 50000,
        'daily_principal_limit_minor' => 200000,
        'unbound_pay_codes' => false,
    ];

    $created = $this->actingAs($maker)
        ->postJson(route('x-change.cockpit.api-partners.production-mandates.store'), $payload)
        ->assertCreated()
        ->assertJsonPath('status', 'awaiting_approval');
    $mandate = PartnerApiProductionMandate::query()->where('reference', $created->json('reference'))->sole();

    expect(Client::query()->count())->toBe(0)
        ->and(PartnerApiClient::query()->count())->toBe(0);

    $this->actingAs($maker)
        ->postJson(route('x-change.cockpit.api-partners.production-mandates.approvals.store', $mandate), [
            'confirm_snapshot' => true,
        ])->assertForbidden();

    $this->actingAs($checker)
        ->postJson(route('x-change.cockpit.api-partners.production-mandates.approvals.store', $mandate), [
            'confirm_snapshot' => true,
        ])->assertOk()->assertJsonPath('status', 'approved');

    expect(Client::query()->count())->toBe(0)
        ->and(PartnerApiClient::query()->count())->toBe(0);

    $credential = $this->actingAs($checker)
        ->postJson(route('x-change.cockpit.api-partners.production-mandates.activations.store', $mandate), [
            'acknowledge_secret_once' => true,
        ])->assertCreated()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('environment', 'production')
        ->assertJsonPath('secret_display', 'one_time_only');

    $secret = (string) $credential->json('client_secret');
    expect($secret)->not->toBeEmpty()
        ->and(PartnerApiClient::query()->sole()->environment)->toBe('production')
        ->and($mandate->refresh()->status->value)->toBe('activated')
        ->and(ExecutionJournalEntry::query()->get()->toJson())->not->toContain($secret);

    $this->actingAs($checker)->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.api-partners.index'))
        ->assertOk()
        ->assertJsonMissing([$secret])
        ->assertJsonMissing(['client_secret']);
});
