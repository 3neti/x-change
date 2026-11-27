<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use LBHurtado\Merchant\Contracts\MerchantProfileRepositoryContract;
use LBHurtado\XChange\Actions\Banking\AssignNetbankAccount;
use LBHurtado\XChange\Actions\Funding\UpdateFundingDestination;
use LBHurtado\XChange\Contracts\CockpitTreasuryAccessContract;
use LBHurtado\XChange\Enums\TreasuryOperatorCapability;
use LBHurtado\XChange\Exceptions\FundingDestinationUnavailable;
use LBHurtado\XChange\Models\BankAccountAssignment;
use LBHurtado\XChange\Models\FundingDestinationPreference;
use LBHurtado\XChange\Models\ProviderAccountLink;
use LBHurtado\XChange\Services\Banking\AssignedBankTreasuryConnection;
use LBHurtado\XChange\Models\TreasuryOperatorAuthorization;
use LBHurtado\XChange\Services\CheckProviderSourceAccountReadiness;
use LBHurtado\XChange\Services\Funding\DefaultFundingDestinationResolver;
use LBHurtado\XChange\Services\Funding\FundingProviderAdapterRegistry;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;
use LBHurtado\XChange\Tests\Fakes\FakeFundingProviderAdapter;

beforeEach(function (): void {
    $this->app->instance(FundingProviderAdapterRegistry::class, new FundingProviderAdapterRegistry([
        new FakeFundingProviderAdapter('netbank'),
    ]));
});

it('shows only the signed-in owner assigned accounts with separate capability readiness', function () {
    $owner = actingAsTestUser();
    $owner->forceFill(['email_verified_at' => now()])->save();
    $other = actingAsTestUser();
    $other->forceFill(['email_verified_at' => now()])->save();
    $assign = app(AssignNetbankAccount::class);
    $assign->handle($owner, [
        'account_number' => '991100001234', 'account_name' => 'Owner Example',
        'customer_id' => '1234', 'vca_alias' => '54321', 'nickname' => 'Payroll',
    ]);
    $assign->handle($other, [
        'account_number' => '991100009999', 'account_name' => 'Other Example',
        'customer_id' => '9876', 'vca_alias' => '54322',
    ]);

    $this->actingAs($owner)->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.accounts.index'))
        ->assertOk()
        ->assertJsonCount(1, 'props.bank_accounts')
        ->assertJsonPath('props.bank_accounts.0.account_number', '991100001234')
        ->assertJsonPath('props.bank_accounts.0.funding_ready', false)
        ->assertJsonPath('props.bank_accounts.0.disbursement_ready', false)
        ->assertJsonMissing(['991100009999', '9876']);
});

it('allows only an authorized administrator to assign a NetBank account', function () {
    $actor = actingAsTestUser();
    $actor->forceFill(['email_verified_at' => now()])->save();
    $owner = actingAsTestUser();
    $owner->forceFill(['email_verified_at' => now()])->save();
    $access = Mockery::mock(CockpitTreasuryAccessContract::class);
    $access->shouldReceive('canManageTreasuryReconciliation')->twice()->andReturn(false, true);
    $this->app->instance(CockpitTreasuryAccessContract::class, $access);
    $data = [
        'owner_id' => $owner->id, 'account_number' => '991100001234', 'account_name' => 'Example',
        'customer_id' => '1234', 'vca_alias' => '54321',
    ];

    $this->actingAs($actor)->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('x-change.cockpit.accounts.bank-accounts.store'), $data)->assertForbidden();
    expect(BankAccountAssignment::query()->count())->toBe(0);
    $this->actingAs($actor)->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('x-change.cockpit.accounts.bank-accounts.store'), $data)->assertRedirect();
    expect(BankAccountAssignment::query()->sole()->owner->is($owner))->toBeTrue();
});

it('allows a delegated human bank-account manager to add a pending account without granting configuration authority', function () {
    enableNetbankTreasuryForTests();
    $administrator = actingAsTestUser();
    $administrator->forceFill(['email_verified_at' => now()])->save();
    $owner = actingAsTestUser();
    $owner->forceFill(['email_verified_at' => now()])->save();
    TreasuryOperatorAuthorization::query()->create([
        'operator_type' => $administrator->getMorphClass(),
        'operator_id' => $administrator->getKey(),
        'capability' => TreasuryOperatorCapability::ManageBankAccounts->value,
        'authorization_reference' => 'test:bank-account-manager',
        'valid_from' => now(),
    ]);

    $this->actingAs($administrator)->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('x-change.cockpit.accounts.bank-accounts.store'), [
            'owner_id' => $owner->getKey(),
            'account_number' => '991100001234',
            'account_name' => 'Example',
            'customer_id' => '1234',
            'vca_alias' => '54321',
        ])->assertRedirect()
        ->assertSessionHas('funding_account_notice', 'Bank account added. Readiness checks are pending.');

    expect(BankAccountAssignment::query()->sole()->owner->is($owner))->toBeTrue();
});

it('forbids a bank-account viewer from adding or configuring accounts', function () {
    enableNetbankTreasuryForTests();
    $viewer = actingAsTestUser();
    $viewer->forceFill(['email_verified_at' => now()])->save();
    $owner = actingAsTestUser();
    $owner->forceFill(['email_verified_at' => now()])->save();
    TreasuryOperatorAuthorization::query()->create([
        'operator_type' => $viewer->getMorphClass(),
        'operator_id' => $viewer->getKey(),
        'capability' => TreasuryOperatorCapability::ViewBankAccounts->value,
        'authorization_reference' => 'test:bank-account-viewer',
        'valid_from' => now(),
    ]);

    $this->actingAs($viewer)->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('x-change.cockpit.accounts.bank-accounts.store'), [
            'owner_id' => $owner->getKey(),
            'account_number' => '991100001234',
            'account_name' => 'Example',
            'customer_id' => '1234',
            'vca_alias' => '54321',
        ])->assertForbidden();

    expect(BankAccountAssignment::query()->count())->toBe(0);

    $assignment = app(AssignNetbankAccount::class)->handle($owner, [
        'account_number' => '991100001234',
        'account_name' => 'Example',
        'customer_id' => '1234',
        'vca_alias' => '54321',
    ]);

    $this->actingAs($viewer)->withSession(['auth.password_confirmed_at' => time()])
        ->patch(route('x-change.cockpit.accounts.bank-accounts.update', $assignment), [
            'action' => 'configure',
            'treasury_connection' => 'personal',
            'disbursement_confirmed' => true,
        ])->assertForbidden();
});

it('exposes delegated account-management permissions independently in the Cockpit page', function () {
    enableNetbankTreasuryForTests();
    $administrator = actingAsTestUser();
    $administrator->forceFill(['email_verified_at' => now()])->save();
    foreach ([
        TreasuryOperatorCapability::ViewBankAccounts,
        TreasuryOperatorCapability::ManageBankAccounts,
    ] as $capability) {
        TreasuryOperatorAuthorization::query()->create([
            'operator_type' => $administrator->getMorphClass(),
            'operator_id' => $administrator->getKey(),
            'capability' => $capability->value,
            'authorization_reference' => 'test:bank-account-administrator:'.$capability->value,
            'valid_from' => now(),
        ]);
    }

    $this->actingAs($administrator)->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.accounts.index'))
        ->assertOk()
        ->assertJsonPath('props.can_view_bank_accounts', true)
        ->assertJsonPath('props.can_manage_bank_accounts', true)
        ->assertJsonPath('props.can_configure_bank_accounts', false);
});

it('does not let a user select another user’s account as default', function () {
    $owner = actingAsTestUser();
    $owner->forceFill(['email_verified_at' => now()])->save();
    $other = actingAsTestUser();
    $other->forceFill(['email_verified_at' => now()])->save();
    $assignment = app(AssignNetbankAccount::class)->handle($owner, [
        'account_number' => '991100001234', 'account_name' => 'Example',
        'customer_id' => '1234', 'vca_alias' => '54321',
    ]);
    $this->actingAs($other)->withSession(['auth.password_confirmed_at' => time()])
        ->patchJson(route('x-change.cockpit.accounts.bank-accounts.update', $assignment->id))
        ->assertUnprocessable();
});

it('lets the owner rename an account without changing its routing details', function () {
    $owner = actingAsTestUser();
    $owner->forceFill(['email_verified_at' => now()])->save();
    $other = actingAsTestUser();
    $other->forceFill(['email_verified_at' => now()])->save();
    $assignment = app(AssignNetbankAccount::class)->handle($owner, [
        'account_number' => '991100001234', 'account_name' => 'Example',
        'customer_id' => '1234', 'vca_alias' => '54321',
    ]);
    $url = route('x-change.cockpit.accounts.bank-accounts.update', $assignment->id);

    $this->actingAs($other)->withSession(['auth.password_confirmed_at' => time()])
        ->patch($url, ['action' => 'rename', 'nickname' => 'Other'])->assertNotFound();
    $this->actingAs($owner)->withSession(['auth.password_confirmed_at' => time()])
        ->patch($url, ['action' => 'rename', 'nickname' => 'Payroll'])->assertRedirect();

    expect($assignment->fresh()->details_ciphertext)
        ->toMatchArray(['nickname' => 'Payroll', 'account_number' => '991100001234', 'customer_id' => '1234']);
});

it('publishes package-owned Accounts routes with verified and PIN-confirmed mutations', function () {
    $page = Route::getRoutes()->getByName('x-change.cockpit.accounts.index');
    $update = Route::getRoutes()->getByName(
        'x-change.cockpit.accounts.providers.funding-destination.update',
    );
    $scenario = Route::getRoutes()->getByName(
        'x-change.cockpit.accounts.scenarios.funding-destinations.store',
    );
    $merchantProfile = Route::getRoutes()->getByName(
        'x-change.cockpit.accounts.funding-qr-merchant-profile.update',
    );

    expect($page)->not->toBeNull()
        ->and($page->gatherMiddleware())->toContain('verified')
        ->and($update)->not->toBeNull()
        ->and($update->gatherMiddleware())->toContain('verified')
        ->and($update->gatherMiddleware())->toContain('password.confirm:settings.security.confirm')
        ->and($update->gatherMiddleware())->toContain('throttle:6,1')
        ->and($scenario)->not->toBeNull()
        ->and($scenario->gatherMiddleware())->toContain('verified')
        ->and($scenario->gatherMiddleware())->toContain('throttle:3,1')
        ->and($scenario->gatherMiddleware())->not->toContain('password.confirm:settings.security.confirm')
        ->and($merchantProfile)->not->toBeNull()
        ->and($merchantProfile->gatherMiddleware())->toContain('verified')
        ->and($merchantProfile->gatherMiddleware())
        ->toContain('password.confirm:settings.security.confirm');
});

it('renders a minimal owner-scoped depositor Account projection with encrypted Inertia history', function () {
    $owner = actingAsTestUser();
    $owner->forceFill(['email_verified_at' => now()])->save();

    $response = $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.accounts.index'));

    $response->assertOk()
        ->assertJsonPath('component', 'x-change/cockpit/Accounts')
        ->assertJsonPath('props.account_overview.schema', 'x-change.cockpit.depositor-account.v1')
        ->assertJsonPath('props.account_overview.status', 'available')
        ->assertJsonPath('props.account_overview.funding_destinations.0.code', 'netbank')
        ->assertJsonPath('props.account_overview.funding_destinations.0.mode', 'shared')
        ->assertJsonPath('props.account_overview.funding_destinations.0.display_reference', 'X Change Treasury · 113001000019')
        ->assertJsonMissingPath('props.account_read_model')
        ->assertJsonMissingPath('props.account_scenario')
        ->assertJsonMissingPath('props.funding_qr_merchant_profile')
        ->assertJsonMissingPath('props.read_model')
        ->assertJsonMissing([
            'test-vca-alias-token',
            'ledger_authority',
            'funding_credit_policy',
            'connection_history',
            'verification_status',
            'can_activate',
            'can_rotate_token',
        ]);

    expect($response->getContent())->toContain('"encryptHistory":true');
});

it('updates the owner payer-visible merchant identity behind confirmation', function () {
    $owner = actingAsTestUser();
    $owner->forceFill(['email_verified_at' => now()])->save();

    $this->withSession(['auth.password_confirmed_at' => time()])
        ->patch(route(
            'x-change.cockpit.accounts.funding-qr-merchant-profile.update',
        ), [
            'name' => 'My Store',
            'city' => 'Makati',
            'merchant_category_code' => '5999',
            'merchant_name_template' => '{name} - {city}',
        ])
        ->assertRedirect()
        ->assertSessionHas(
            'funding_account_notice',
            'Merchant identity updated. New Pay Codes and Funding QR codes will use it.',
        );

    $merchant = app(MerchantProfileRepositoryContract::class)->findForUser($owner);

    expect($merchant)->not->toBeNull()
        ->and($merchant->name)->toBe('My Store')
        ->and($merchant->city)->toBe('Makati')
        ->and($merchant->merchant_category_code)->toBe('5999')
        ->and($merchant->merchant_name_template)->toBe('{name} - {city}');
});

it('runs the protected Cockpit account-management walkthrough without durable state', function () {
    config()->set('x-change.cockpit.account_scenario.enabled', true);
    $owner = actingAsTestUser();
    $owner->forceFill(['email_verified_at' => now()])->save();
    config()->set('x-change.lifecycle.defaults.user_model', $owner::class);
    $balanceBefore = (int) $owner->wallet->balance;

    $response = $this->postJson(route(
        'x-change.cockpit.accounts.scenarios.funding-destinations.store',
    ));

    $response->assertOk()
        ->assertJsonPath('schema', 'x-change.lifecycle.account-management-scenario.v1')
        ->assertJsonPath('success', true)
        ->assertJsonPath('rollback_completed', true)
        ->assertJsonPath('simulation.provider_calls', 0)
        ->assertJsonPath('simulation.balance_changed', false)
        ->assertJsonCount(7, 'steps')
        ->assertJsonPath('steps.3.key', 'netbank_registration_token_boundary')
        ->assertJsonPath('steps.4.outcome', 'blocked')
        ->assertJsonMissing([
            '991100004242',
            'SCENARIO-DEMO-WALLET-654321',
            'scenario-write-only-netbank-token',
        ]);

    expect(FundingDestinationPreference::query()->count())->toBe(0)
        ->and(ProviderAccountLink::query()->count())->toBe(0)
        ->and((int) $owner->wallet->refresh()->balance)->toBe($balanceBefore);
});

it('refuses the Cockpit walkthrough when its environment gate is disabled', function () {
    config()->set('x-change.cockpit.account_scenario.enabled', false);
    $owner = actingAsTestUser();
    $owner->forceFill(['email_verified_at' => now()])->save();

    $this->postJson(route(
        'x-change.cockpit.accounts.scenarios.funding-destinations.store',
    ))->assertForbidden();
});

it('stores dedicated NetBank routing without generating or persisting a token', function () {
    $owner = actingAsTestUser();

    $preference = app(UpdateFundingDestination::class)->handle($owner, 'netbank', [
        'mode' => 'dedicated',
        'account_number' => '991100001234',
        'account_name' => 'Dedicated Treasury',
        'vca_alias' => '54321',
    ]);
    $link = $preference->providerAccountLink()->firstOrFail();
    $raw = DB::table('xchange_provider_account_links')->find($link->getKey());

    expect($preference->mode)->toBe('dedicated')
        ->and($link->verification_status)->toBe('routing_configured')
        ->and($link->display_reference)->toBe('•••• 1234 · VCA 54321')
        ->and($link->routing_profile_ciphertext)->not->toHaveKey('vca_alias_token')
        ->and($raw->routing_profile_ciphertext)->not->toContain('991100001234')
        ->and($raw->routing_profile_ciphertext)->not->toContain('vca_alias_token')
        ->and($this->fakeAuditLogger->hasEvent('funding.destination.selected'))->toBeTrue();
});

it('configures a dedicated destination using a provider-neutral adapter', function () {
    $owner = actingAsTestUser();
    $this->app->instance(FundingProviderAdapterRegistry::class, new FundingProviderAdapterRegistry([
        new FakeFundingProviderAdapter('example'),
    ]));

    $preference = app(UpdateFundingDestination::class)->handle($owner, 'example', [
        'mode' => 'dedicated',
        'account_number' => '991100001234',
        'account_name' => 'Example Treasury',
        'vca_alias' => '54321',
    ]);

    expect($preference->provider_code)->toBe('example')
        ->and($preference->providerAccountLink->provider)->toBe('example')
        ->and($preference->providerAccountLink->routing_fingerprint)->toBe(hash('sha256', 'example|991100001234|54321'));
});

it('records a new routing configuration without treating token generation as enrollment', function () {
    $owner = actingAsTestUser();
    $update = app(UpdateFundingDestination::class);
    $data = [
        'mode' => 'dedicated',
        'account_number' => '991100001234',
        'account_name' => 'Dedicated Treasury',
        'vca_alias' => '54321',
    ];

    $first = $update->handle($owner, 'netbank', $data);
    $second = $update->handle($owner, 'netbank', $data);

    expect($first->providerAccountLink)->not->toBeNull()
        ->and($second->providerAccountLink)->not->toBeNull()
        ->and($second->providerAccountLink?->is($first->providerAccountLink))->toBeTrue()
        ->and(ProviderAccountLink::query()->count())->toBe(1)
        ->and(ProviderAccountLink::query()->get()->every(
            fn (ProviderAccountLink $link): bool => ! array_key_exists(
                'vca_alias_token',
                (array) $link->routing_profile_ciphertext,
            ),
        ))->toBeTrue();
});

it('resolves only the owner’s matching dedicated provider destination', function () {
    $owner = actingAsTestUser();
    $preference = app(UpdateFundingDestination::class)->handle($owner, 'netbank', [
        'mode' => 'dedicated',
        'account_number' => '991100001234',
        'account_name' => 'Dedicated Treasury',
        'vca_alias' => '54321',
    ]);
    $resolver = app(DefaultFundingDestinationResolver::class);
    $destination = $resolver->resolve($owner, 'netbank', 'account-1');

    expect($destination->bankAccountNumber)->toBe('991100001234')
        ->and($destination->accountReference)->toBe('account-1');

    $preference->providerAccountLink->forceFill(['provider' => 'paynamics'])->save();
    expect(fn () => $resolver->resolve($owner, 'netbank', 'account-1'))
        ->toThrow(FundingDestinationUnavailable::class);
});

it('returns a destination to shared mode without deleting its connection history', function () {
    $owner = actingAsTestUser();

    $preference = app(UpdateFundingDestination::class)->handle($owner, 'netbank', [
        'mode' => 'shared',
    ]);

    expect($preference->mode)->toBe('shared')
        ->and($preference->provider_account_link_id)->toBeNull()
        ->and(ProviderAccountLink::query()->count())->toBe(0);
});

it('allows only an authorized administrator to configure a checked personal account', function () {
    $owner = actingAsTestUser();
    $owner->forceFill(['email_verified_at' => now()])->save();
    $assignment = app(AssignNetbankAccount::class)->handle($owner, [
        'account_number' => '991100001234', 'account_name' => 'Example', 'customer_id' => '1234', 'vca_alias' => '54321',
    ]);
    $access = Mockery::mock(CockpitTreasuryAccessContract::class);
    $access->shouldReceive('canManageTreasuryReconciliation')->twice()->andReturn(false, true);
    app()->instance(CockpitTreasuryAccessContract::class, $access);
    $catalog = new TreasuryProviderConnectionCatalog(['personal' => [
        'provider' => 'netbank', 'mode' => 'required', 'currency' => 'PHP',
        'inventory_reference' => 'inventory:personal', 'settlement_resource_reference' => 'resource:personal',
        'settlement_resource_type' => 'cash_at_bank', 'custody_mode' => 'provider_projection',
    ]]);
    app()->instance(TreasuryProviderConnectionCatalog::class, $catalog);
    $readiness = Mockery::mock(CheckProviderSourceAccountReadiness::class);
    $readiness->shouldReceive('handle')->once()->with(0, 'netbank', '991100001234')
        ->andReturn(['ready' => true, 'checked' => true, 'currency' => 'PHP', 'available_balance_minor' => 0]);
    app()->instance(CheckProviderSourceAccountReadiness::class, $readiness);
    $url = route('x-change.cockpit.accounts.bank-accounts.update', $assignment->id);
    $data = ['action' => 'configure', 'treasury_connection' => 'personal', 'disbursement_confirmed' => true];
    $this->actingAs($owner)->withSession(['auth.password_confirmed_at' => time()])
        ->patchJson($url, ['action' => 'configure', 'treasury_connection' => 'personal', 'disbursement_confirmed' => false])
        ->assertUnprocessable();
    $this->actingAs($owner)->withSession(['auth.password_confirmed_at' => time()])->patch($url, $data)->assertForbidden();
    expect($assignment->fresh()->treasury_connection_reference)->toBeNull();
    $this->actingAs($owner)->withSession(['auth.password_confirmed_at' => time()])->patch($url, $data)->assertRedirect();
    expect($assignment->fresh()->treasury_connection_reference)->toBe('personal')
        ->and($assignment->fresh()->providerAccountLink->capabilities)->toContain('disbursement')->not->toContain('funding');
});

it('does not display account operations as ready without a separate Treasury binding', function () {
    $owner = actingAsTestUser();
    $owner->forceFill(['email_verified_at' => now()])->save();
    $assignment = app(AssignNetbankAccount::class)->handle($owner, [
        'account_number' => '991100001234', 'account_name' => 'Example', 'customer_id' => '1234', 'vca_alias' => '54321',
    ]);
    $assignment->providerAccountLink->forceFill([
        'status' => 'ready', 'ready_at' => now(), 'capabilities' => ['funding', 'disbursement'],
    ])->save();
    $this->actingAs($owner)->withHeader('X-Inertia', 'true')->get(route('x-change.cockpit.accounts.index'))
        ->assertOk()->assertJsonPath('props.bank_accounts.0.funding_ready', false)
        ->assertJsonPath('props.bank_accounts.0.disbursement_ready', false);
});

it('shows the selected assigned bank account on issuance only to its owner', function () {
    config()->set('x-change.provider_runtime.default_provider', 'netbank');
    $owner = actingAsTestUser();
    $owner->forceFill(['email_verified_at' => now()])->save();
    $assignment = app(AssignNetbankAccount::class)->handle($owner, [
        'account_number' => '991-100-00123-4', 'account_name' => 'Example Corp', 'customer_id' => '1234', 'vca_alias' => '54321',
    ]);
    FundingDestinationPreference::query()->create([
        'owner_type' => $owner->getMorphClass(), 'owner_id' => $owner->getKey(), 'provider_code' => 'netbank',
        'mode' => 'dedicated', 'provider_account_link_id' => $assignment->provider_account_link_id,
    ]);
    $this->actingAs($owner)->withHeader('X-Inertia', 'true')->get(route('x-change.cockpit.quick-generate'))
        ->assertOk()->assertJsonPath('props.issuance_bank_account.name', 'Example Corp')
        ->assertJsonPath('props.issuance_bank_account.number', '991-100-00123-4')
        ->assertJsonPath('props.issuance_bank_account.mode', 'dedicated');
    $other = actingAsTestUser();
    $other->forceFill(['email_verified_at' => now()])->save();
    $this->actingAs($other)->withHeader('X-Inertia', 'true')->get(route('x-change.cockpit.quick-generate'))
        ->assertOk()->assertJsonMissing(['991-100-00123-4', 'Example Corp']);
});

it('limits funding setup to administrators who reviewed VCA history', function () {
    $owner = actingAsTestUser();
    $owner->forceFill(['email_verified_at' => now()])->save();
    $assignment = app(AssignNetbankAccount::class)->handle($owner, [
        'account_number' => '991100001234', 'account_name' => 'Example', 'customer_id' => '1234', 'vca_alias' => '54321',
    ]);
    config()->set('x-change.funding.dedicated_netbank_vca_enabled', true);
    $catalog = new TreasuryProviderConnectionCatalog(['personal' => [
        'provider' => 'netbank', 'mode' => 'required', 'currency' => 'PHP',
        'inventory_reference' => 'inventory:personal', 'settlement_resource_reference' => 'resource:personal',
        'settlement_resource_type' => 'cash_at_bank', 'custody_mode' => 'provider_projection',
    ]]);
    app()->instance(TreasuryProviderConnectionCatalog::class, $catalog);
    app(AssignedBankTreasuryConnection::class)->bind($owner, $assignment->id, 'personal');
    $access = Mockery::mock(CockpitTreasuryAccessContract::class);
    $access->shouldReceive('canManageTreasuryReconciliation')->twice()->andReturn(false, true);
    app()->instance(CockpitTreasuryAccessContract::class, $access);
    $url = route('x-change.cockpit.accounts.bank-accounts.update', $assignment->id);
    $session = ['auth.password_confirmed_at' => time()];

    $this->actingAs($owner)->withSession($session)->patchJson($url, ['action' => 'configure_funding', 'history_reviewed' => false])->assertUnprocessable();
    $this->actingAs($owner)->withSession($session)->patch($url, ['action' => 'configure_funding', 'history_reviewed' => true])->assertForbidden();
    $this->actingAs($owner)->withSession($session)->patch($url, ['action' => 'configure_funding', 'history_reviewed' => true])->assertRedirect();

    expect($assignment->fresh()->providerAccountLink->capabilities)->toContain('funding')->not->toContain('disbursement');
});

it('shows personal funding as ready only while the funding capability is enabled', function () {
    $owner = actingAsTestUser();
    $owner->forceFill(['email_verified_at' => now()])->save();
    $assignment = app(AssignNetbankAccount::class)->handle($owner, [
        'account_number' => '991100001234', 'account_name' => 'Example', 'customer_id' => '1234', 'vca_alias' => '54321',
    ]);
    $catalog = new TreasuryProviderConnectionCatalog(['personal' => [
        'provider' => 'netbank', 'mode' => 'required', 'currency' => 'PHP',
        'inventory_reference' => 'inventory:personal', 'settlement_resource_reference' => 'resource:personal',
        'settlement_resource_type' => 'cash_at_bank', 'custody_mode' => 'provider_projection',
    ]]);
    app()->instance(TreasuryProviderConnectionCatalog::class, $catalog);
    app(AssignedBankTreasuryConnection::class)->bind($owner, $assignment->id, 'personal');
    $assignment->providerAccountLink->forceFill(['status' => 'ready', 'ready_at' => now(), 'capabilities' => ['funding']])->save();
    $url = route('x-change.cockpit.accounts.index');

    config()->set('x-change.funding.dedicated_netbank_vca_enabled', true);
    $this->actingAs($owner)->withHeader('X-Inertia', 'true')->get($url)->assertOk()->assertJsonPath('props.bank_accounts.0.funding_ready', true);
    config()->set('x-change.funding.dedicated_netbank_vca_enabled', false);
    $this->actingAs($owner)->withHeader('X-Inertia', 'true')->get($url)->assertOk()->assertJsonPath('props.bank_accounts.0.funding_ready', false);
});

it('lets a treasury administrator see accounts assigned to other users without exposing them to account holders', function () {
    $admin = actingAsTestUser();
    $owner = actingAsTestUser();
    foreach ([$admin, $owner] as $user) {
        $user->forceFill(['email_verified_at' => now()])->save();
    }
    $assignment = app(AssignNetbankAccount::class)->handle($owner, [
        'account_number' => '991100001234', 'account_name' => 'Example', 'customer_id' => '1234', 'vca_alias' => '54321',
    ]);
    $access = Mockery::mock(CockpitTreasuryAccessContract::class);
    $access->shouldReceive('canManageTreasuryReconciliation')->andReturnUsing(fn ($user) => $user->is($admin));
    $access->shouldReceive('canViewTreasuryControls')->andReturn(false);
    $access->shouldReceive('canRefreshProviderLiquidity')->andReturn(false);
    app()->instance(CockpitTreasuryAccessContract::class, $access);
    $url = route('x-change.cockpit.accounts.index');

    $this->actingAs($admin)->withHeader('X-Inertia', 'true')->get($url)->assertOk()
        ->assertJsonPath('props.bank_accounts.0.id', $assignment->id)
        ->assertJsonPath('props.bank_accounts.0.owner_id', (string) $owner->id)
        ->assertJsonPath('props.bank_accounts.0.is_owner', false);
    $this->actingAs($owner)->withHeader('X-Inertia', 'true')->get($url)->assertOk()
        ->assertJsonPath('props.bank_accounts.0.is_owner', true)
        ->assertJsonPath('props.bank_accounts.0.owner_id', null);
    $other = actingAsTestUser();
    $other->forceFill(['email_verified_at' => now()])->save();
    $this->actingAs($other)->withHeader('X-Inertia', 'true')->get($url)->assertOk()
        ->assertJsonCount(0, 'props.bank_accounts');
});

it('restricts fresh funding commissioning to treasury administrators', function () {
    $owner = actingAsTestUser();
    $owner->forceFill(['email_verified_at' => now()])->save();
    $assignment = app(AssignNetbankAccount::class)->handle($owner, [
        'account_number' => '991100001234', 'account_name' => 'Example', 'customer_id' => '1234', 'vca_alias' => '54321',
    ]);
    config()->set('x-change.funding.dedicated_netbank_vca_enabled', true);
    $catalog = new TreasuryProviderConnectionCatalog(['personal' => [
        'provider' => 'netbank', 'mode' => 'required', 'currency' => 'PHP',
        'inventory_reference' => 'inventory:personal', 'settlement_resource_reference' => 'resource:personal',
        'settlement_resource_type' => 'cash_at_bank', 'custody_mode' => 'provider_projection',
    ]]);
    app()->instance(TreasuryProviderConnectionCatalog::class, $catalog);
    app(AssignedBankTreasuryConnection::class)->bind($owner, $assignment->id, 'personal');
    $access = Mockery::mock(CockpitTreasuryAccessContract::class);
    $access->shouldReceive('canManageTreasuryReconciliation')->twice()->andReturn(false, true);
    app()->instance(CockpitTreasuryAccessContract::class, $access);
    $url = route('x-change.cockpit.accounts.bank-accounts.update', $assignment->id);
    $session = ['auth.password_confirmed_at' => time()];
    $data = ['action' => 'configure_fresh_funding', 'sole_writer_confirmed' => true, 'known_vcas' => ['543210000001']];

    $this->actingAs($owner)->withSession($session)->patch($url, $data)->assertForbidden();
    $this->actingAs($owner)->withSession($session)->patch($url, $data)->assertRedirect();
    expect(data_get($assignment->fresh()->providerAccountLink->metadata, 'funding_assignment.scope'))->toBe('fresh_intents_only');
});
