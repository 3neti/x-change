<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use LBHurtado\XChange\Lifecycle\Scenarios\LifecycleScenarioEngine;
use LBHurtado\XChange\Lifecycle\Scenarios\LifecycleScenarioRunOptions;
use LBHurtado\XChange\Services\Commercial\CommercialPrincipalProvisioningService;
use LBHurtado\XChange\Services\Commercial\ProvisionCommercialBaselines;
use LBHurtado\XChange\Tests\Fakes\User as FakeLifecycleUser;

function preparePublicAutoRegistrationLifecycle(): FakeLifecycleUser
{
    config()->set('x-change.lifecycle.defaults.user_model', FakeLifecycleUser::class);
    config()->set('x-change.lifecycle.public_auto_register.enabled', true);
    config()->set('x-change.onboarding.email_fallback_domain', 'example.test');
    config()->set('x-change.commercial.principal.reference', 'public-auto-register-commercial');
    config()->set('x-change.commercial.principal.legal_name', 'Public Auto-Register Commercial Principal');
    config()->set('x-change.commercial.principal.authorization_reference', 'commissioning:public-auto-register:test');
    config()->set('x-change.commercial.enabled', false);
    enableNetbankTreasuryForTests();
    config()->set('x-change.public_auto_register.treasury_connection_reference', 'netbank-primary');
    config()->set('x-change.funding.providers.qrph_simulator.treasury', [
        'inventory_reference' => 'inventory:qrph-simulator:vca-cash',
        'settlement_resource_reference' => 'resource:qrph-simulator:corporate-vca',
        'resource_type' => 'cash_at_bank',
    ]);
    config()->set('x-change.commercial.enabled', true);
    config()->set('x-change.commercial.billing.mode', 'billable');
    app(ProvisionCommercialBaselines::class)->provision('commissioning-manifest:public-auto-register-test');
    app(CommercialPrincipalProvisioningService::class)->provision();

    $issuer = FakeLifecycleUser::query()->create([
        'name' => 'Auto Registration Operator',
        'email' => 'auto-register-operator@example.test',
        'password' => bcrypt('password'),
        'mobile' => '639173011987',
        'mobile_verified_at' => now(),
    ]);

    fundTestUserWallet($issuer);
    fundTestUserClientFunds($issuer, 10_000);
    fundTestUserClientFunds(app(\LBHurtado\XChange\Contracts\CommercialPrincipalResolverContract::class)->resolve(), 10_000);

    return $issuer;
}

function runPublicAutoRegistrationLifecycle(FakeLifecycleUser $issuer): mixed
{
    $command = new class extends Command
    {
        public function option($key = null): mixed
        {
            return $key === 'json';
        }
    };

    return app(LifecycleScenarioEngine::class)->run(
        command: $command,
        scenarioKey: 'public_auto_register_demo',
        options: new LifecycleScenarioRunOptions(
            issuer: (string) $issuer->getKey(),
            claimMobile: '09177210752',
            json: true,
        ),
    );
}

it('runs the complete mocked public auto-registration path and rolls it back', function () {
    Http::preventStrayRequests();
    $issuer = preparePublicAutoRegistrationLifecycle();
    $countsBefore = [
        DB::table('users')->count(),
        DB::table('x_change_public_auto_registrations')->count(),
        DB::table('x_change_funding_intents')->count(),
        DB::table('x_change_simulated_funding_transactions')->count(),
        DB::table('webhook_receipts')->count(),
        DB::table('provider_funding_observations')->count(),
        DB::table('x_change_funding_settlements')->count(),
    ];

    $result = runPublicAutoRegistrationLifecycle($issuer);

    expect($result->exitCode)->toBe(Command::SUCCESS)
        ->and(data_get($result->payload, 'schema'))->toBe('x-change.lifecycle.public-auto-register.v1')
        ->and(data_get($result->payload, 'success'))->toBeTrue()
        ->and(data_get($result->payload, 'rollback_completed'))->toBeTrue()
        ->and(data_get($result->payload, 'simulation.provider_calls'))->toBe(0)
        ->and(data_get($result->payload, 'steps'))->toHaveCount(5)
        ->and(data_get($result->payload, 'steps.0.key'))->toBe('registration_started')
        ->and(data_get($result->payload, 'steps.2.key'))->toBe('payment_settled')
        ->and(data_get($result->payload, 'steps.3.key'))->toBe('account_created_and_funded')
        ->and(data_get($result->payload, 'steps.4.key'))->toBe('completion_replay_noop')
        ->and(data_get($result->payload, 'balance.recipient_minor'))->toBe(10_000)
        ->and(data_get($result->payload, 'balance.recipient_after_replay_minor'))->toBe(10_000)
        ->and([
            DB::table('users')->count(),
            DB::table('x_change_public_auto_registrations')->count(),
            DB::table('x_change_funding_intents')->count(),
            DB::table('x_change_simulated_funding_transactions')->count(),
            DB::table('webhook_receipts')->count(),
            DB::table('provider_funding_observations')->count(),
            DB::table('x_change_funding_settlements')->count(),
        ])->toBe($countsBefore);

    expect(json_encode($result->payload, JSON_THROW_ON_ERROR))
        ->not->toContain('09177210752')
        ->not->toContain('public-auto-register-lifecycle-signing-key')
        ->not->toContain('public-auto-register-lifecycle-mobile-key');
});

it('runs public auto-registration through the lifecycle command', function () {
    $issuer = preparePublicAutoRegistrationLifecycle();

    $this->artisan('xchange:lifecycle:run', [
        'scenario' => 'public_auto_register_demo',
        '--issuer' => (string) $issuer->getKey(),
        '--claim-mobile' => '09177210752',
        '--json' => true,
    ])->assertSuccessful();

    expect(DB::table('x_change_public_auto_registrations')->count())->toBe(0)
        ->and(DB::table('x_change_funding_settlements')->count())->toBe(0);
});
