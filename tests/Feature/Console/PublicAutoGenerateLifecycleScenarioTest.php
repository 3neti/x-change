<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Lifecycle\Scenarios\LifecycleScenarioEngine;
use LBHurtado\XChange\Lifecycle\Scenarios\LifecycleScenarioRunOptions;
use LBHurtado\XChange\Models\CommercialPrincipal;
use LBHurtado\XChange\Models\FundingIntent;
use LBHurtado\XChange\Models\FundingSettlement;
use LBHurtado\XChange\Models\PayCodeIssuanceFundingOrder;
use LBHurtado\XChange\Models\SimulatedFundingTransaction;
use LBHurtado\XChange\Services\Commercial\CommercialPrincipalProvisioningService;
use LBHurtado\XChange\Services\Commercial\ProvisionCommercialBaselines;
use LBHurtado\XChange\Tests\Fakes\User as FakeLifecycleUser;

function preparePublicAutoGenerateLifecycle(): array
{
    config()->set('x-change.lifecycle.defaults.user_model', FakeLifecycleUser::class);
    config()->set('x-change.lifecycle.qrph_funding_simulation.enabled', true);
    config()->set('x-change.commercial.principal.reference', 'public-auto-generate-commercial');
    config()->set('x-change.commercial.principal.legal_name', 'Public Auto-Generate Commercial Principal');
    config()->set(
        'x-change.commercial.principal.authorization_reference',
        'commissioning:public-auto-generate:test',
    );
    enableNetbankTreasuryForTests();
    app(ProvisionCommercialBaselines::class)->provision('commissioning-manifest:public-auto-generate-test');
    app(CommercialPrincipalProvisioningService::class)->provision();
    $principal = CommercialPrincipal::query()
        ->where('reference', 'public-auto-generate-commercial')
        ->sole();
    $principal->getWallet('platform') ?? $principal->createWallet([
        'name' => 'Platform Wallet',
        'slug' => 'platform',
    ]);
    $issuer = FakeLifecycleUser::query()->create([
        'name' => 'Public Scenario Harness',
        'email' => 'public-scenario@example.test',
        'password' => bcrypt('password'),
    ]);
    $issuer->forceFill([
        'mobile' => '639173011987',
        'mobile_verified_at' => now(),
    ])->save();

    return [$issuer, $principal];
}

it('runs public Auto-Generate through exact simulated funding and rolls everything back', function (): void {
    Http::preventStrayRequests();
    [$issuer, $principal] = preparePublicAutoGenerateLifecycle();
    $balanceBefore = (int) $principal->getWallet('platform')->balance;
    $countsBefore = [
        Voucher::query()->count(),
        FundingIntent::query()->count(),
        PayCodeIssuanceFundingOrder::query()->count(),
        SimulatedFundingTransaction::query()->count(),
        FundingSettlement::query()->count(),
        DB::table('webhook_receipts')->count(),
        DB::table('provider_funding_observations')->count(),
    ];
    $command = new class extends Command
    {
        public function option($key = null): mixed
        {
            return $key === 'json';
        }
    };

    $result = app(LifecycleScenarioEngine::class)->run(
        command: $command,
        scenarioKey: 'public_auto_generate_demo',
        options: new LifecycleScenarioRunOptions(
            issuer: (string) $issuer->getKey(),
            json: true,
        ),
    );

    expect(data_get($result->payload, 'validation_errors', []))->toBe([])
        ->and(data_get($result->payload, 'failure_message'))->toBeNull()
        ->and(data_get($result->payload, 'failure'))->toBeNull()
        ->and($result->exitCode)->toBe(Command::SUCCESS)
        ->and(data_get($result->payload, 'schema'))->toBe('x-change.lifecycle.public-auto-generate.v1')
        ->and(data_get($result->payload, 'success'))->toBeTrue()
        ->and(data_get($result->payload, 'rollback_completed'))->toBeTrue()
        ->and(data_get($result->payload, 'simulation.provider_calls'))->toBe(0)
        ->and(data_get($result->payload, 'artifacts.commercial_principal_reference'))
        ->toBe('public-auto-generate-commercial')
        ->and(data_get($result->payload, 'artifacts.claim_qr_present'))->toBeTrue()
        ->and(data_get($result->payload, 'artifacts.share_result_present'))->toBeTrue()
        ->and(data_get($result->payload, 'artifacts.expected_payment_minor'))->toBe(4_300)
        ->and(data_get($result->payload, 'steps'))->toHaveCount(6)
        ->and(data_get($result->payload, 'steps.2.key'))->toBe('guest_order_bound')
        ->and(data_get($result->payload, 'steps.5.key'))->toBe('pay_code_ready')
        ->and([
            Voucher::query()->count(),
            FundingIntent::query()->count(),
            PayCodeIssuanceFundingOrder::query()->count(),
            SimulatedFundingTransaction::query()->count(),
            FundingSettlement::query()->count(),
            DB::table('webhook_receipts')->count(),
            DB::table('provider_funding_observations')->count(),
        ])->toBe($countsBefore)
        ->and((int) $principal->getWallet('platform')->refresh()->balance)->toBe($balanceBefore);

    expect(json_encode($result->payload, JSON_THROW_ON_ERROR))
        ->not->toContain('639173011987')
        ->not->toContain('public-auto-generate-lifecycle-signing-key')
        ->not->toContain('public-auto-generate-lifecycle-mobile-key')
        ->not->toContain('X-XChange-Guest-Order-Token');
});

it('runs the public Auto-Generate rollback lifecycle through the package command', function (): void {
    [$issuer] = preparePublicAutoGenerateLifecycle();

    $this->artisan('xchange:lifecycle:run', [
        'scenario' => 'public_auto_generate_demo',
        '--issuer' => (string) $issuer->getKey(),
        '--json' => true,
    ])->assertSuccessful();

    expect(PayCodeIssuanceFundingOrder::query()->count())->toBe(0)
        ->and(SimulatedFundingTransaction::query()->count())->toBe(0)
        ->and(FundingSettlement::query()->count())->toBe(0);
});

it('runs and renders the rollback lifecycle through the operator browser surface', function (): void {
    Http::preventStrayRequests();
    config()->set('x-change.lifecycle.public_auto_generate.browser_enabled', true);
    [$operator] = preparePublicAutoGenerateLifecycle();
    $this->actingAs($operator);

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.campaigns.public-auto-generate-scenario-runner.show'))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/cockpit/PublicAutoGenerateScenarioRunner')
        ->assertJsonPath('props.run', null);

    $this->post(route('x-change.cockpit.campaigns.public-auto-generate-scenario-runner.store'))
        ->assertRedirect(route('x-change.cockpit.campaigns.public-auto-generate-scenario-runner.show'))
        ->assertSessionHas('x-change.lifecycle.public-auto-generate.browser-result.success', true)
        ->assertSessionHas('x-change.lifecycle.public-auto-generate.browser-result.rollback_completed', true)
        ->assertSessionHas('x-change.lifecycle.public-auto-generate.browser-result.simulation.provider_calls', 0)
        ->assertSessionHas('x-change.lifecycle.public-auto-generate.browser-result.artifacts.expected_payment_minor', 4_300);

    $this->actingAs($operator);

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.campaigns.public-auto-generate-scenario-runner.show'))
        ->assertOk()
        ->assertJsonPath('props.run.success', true)
        ->assertJsonPath('props.run.rollback_completed', true)
        ->assertJsonPath('props.run.steps.0.key', 'principal_resolved')
        ->assertJsonPath('props.run.steps.5.key', 'pay_code_ready')
        ->assertJsonPath('props.run.artifacts.expected_payment_minor', 4_300);

    expect(PayCodeIssuanceFundingOrder::query()->count())->toBe(0)
        ->and(SimulatedFundingTransaction::query()->count())->toBe(0)
        ->and(FundingSettlement::query()->count())->toBe(0);
});
