<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use LBHurtado\XChange\Lifecycle\Scenarios\LifecycleScenarioEngine;
use LBHurtado\XChange\Lifecycle\Scenarios\LifecycleScenarioRunOptions;
use LBHurtado\XChange\Models\FundingIntent;
use LBHurtado\XChange\Models\FundingSettlement;
use LBHurtado\XChange\Models\MobileVerificationChallenge;
use LBHurtado\XChange\Models\SimulatedFundingTransaction;
use LBHurtado\XChange\Tests\Fakes\User as FakeLifecycleUser;

function runUnknownMobileOnboardingScenario(FakeLifecycleUser $issuer): mixed
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
        scenarioKey: 'qrph_funding_unknown_mobile_onboarding_demo',
        options: new LifecycleScenarioRunOptions(
            issuer: (string) $issuer->getKey(),
            json: true,
        ),
    );
}

it('stops an unknown mobile, onboards it explicitly, funds once, and rolls everything back', function () {
    Http::preventStrayRequests();
    config()->set('x-change.lifecycle.defaults.user_model', FakeLifecycleUser::class);
    config()->set('x-change.lifecycle.qrph_funding_simulation.enabled', true);
    $issuer = actingAsTestUser();
    $balanceBefore = (int) $issuer->wallet->balance;
    $countsBefore = [
        FakeLifecycleUser::query()->count(),
        MobileVerificationChallenge::query()->count(),
        FundingIntent::query()->count(),
        SimulatedFundingTransaction::query()->count(),
        FundingSettlement::query()->count(),
        DB::table('webhook_receipts')->count(),
        DB::table('provider_funding_observations')->count(),
    ];

    $result = runUnknownMobileOnboardingScenario($issuer);

    expect($result->exitCode)->toBe(Command::SUCCESS)
        ->and(data_get($result->payload, 'schema'))->toBe(
            'x-change.lifecycle.qrph-unknown-mobile-onboarding.v1',
        )
        ->and(data_get($result->payload, 'success'))->toBeTrue()
        ->and(data_get($result->payload, 'rollback_completed'))->toBeTrue()
        ->and(data_get($result->payload, 'simulation.webhook_user_creation'))->toBeFalse()
        ->and(data_get($result->payload, 'simulation.payment_before_identity'))->toBeFalse()
        ->and(data_get($result->payload, 'funding.credited_minor'))->toBe(2_500)
        ->and(data_get($result->payload, 'funding.replay_noop'))->toBeTrue()
        ->and(data_get($result->payload, 'steps'))->toHaveCount(11)
        ->and(data_get($result->payload, 'steps.1.key'))->toBe('unknown_mobile_blocked')
        ->and(data_get($result->payload, 'steps.1.facts.1.value'))->toBe('No')
        ->and(data_get($result->payload, 'steps.2.key'))->toBe('mobile_user_onboarded')
        ->and(data_get($result->payload, 'steps.3.key'))->toBe('mobile_verified_and_account_opened')
        ->and([
            FakeLifecycleUser::query()->count(),
            MobileVerificationChallenge::query()->count(),
            FundingIntent::query()->count(),
            SimulatedFundingTransaction::query()->count(),
            FundingSettlement::query()->count(),
            DB::table('webhook_receipts')->count(),
            DB::table('provider_funding_observations')->count(),
        ])->toBe($countsBefore)
        ->and((int) $issuer->wallet->refresh()->balance)->toBe($balanceBefore);

    expect(json_encode($result->payload, JSON_THROW_ON_ERROR))
        ->not->toContain('639175550123')
        ->not->toContain('000000');
});

it('refuses the unknown-mobile scenario when the lifecycle gate is disabled', function () {
    config()->set('x-change.lifecycle.defaults.user_model', FakeLifecycleUser::class);
    config()->set('x-change.lifecycle.qrph_funding_simulation.enabled', false);
    $issuer = actingAsTestUser();

    $result = runUnknownMobileOnboardingScenario($issuer);

    expect($result->exitCode)->toBe(Command::FAILURE)
        ->and(data_get($result->payload, 'success'))->toBeFalse()
        ->and(data_get($result->payload, 'message'))->toContain('disabled');
});

it('fails closed when the configured unknown mobile already belongs to a user', function () {
    config()->set('x-change.lifecycle.defaults.user_model', FakeLifecycleUser::class);
    config()->set('x-change.lifecycle.qrph_funding_simulation.enabled', true);
    $issuer = actingAsTestUser();
    $existing = FakeLifecycleUser::query()->create([
        'name' => 'Existing Mobile User',
        'email' => 'existing-mobile@example.test',
        'password' => bcrypt('password'),
    ]);
    $existing->forceFill([
        'mobile' => '639175550123',
        'mobile_verified_at' => now(),
    ])->save();
    $intentCountBefore = FundingIntent::query()->count();

    $result = runUnknownMobileOnboardingScenario($issuer);

    expect($result->exitCode)->toBe(Command::FAILURE)
        ->and(data_get($result->payload, 'success'))->toBeFalse()
        ->and(data_get($result->payload, 'rollback_completed'))->toBeTrue()
        ->and(FundingIntent::query()->count())->toBe($intentCountBefore)
        ->and($existing->refresh()->getAttribute('mobile_verified_at'))->not->toBeNull();
});
