<?php

declare(strict_types=1);

use Bavix\Wallet\Interfaces\Wallet;
use Illuminate\Support\Facades\Artisan;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Contracts\SystemUserResolverContract;
use LBHurtado\XChange\Tests\Fakes\User;
use Symfony\Component\Console\Output\BufferedOutput;

beforeEach(function (): void {
    config()->set('x-change.lifecycle.defaults.user_model', User::class);
    config()->set('x-change.lifecycle.defaults.system_user_email', 'system@example.test');
    config()->set('x-change.lifecycle.defaults.test_user_email', 'lester@hurtado.ph');
    config()->set('x-change.lifecycle.defaults.test_user_mobile', '09173011987');
    config()->set('x-change.onboarding.voucher.require_otp', true);
    config()->set('queue.default', 'sync');

    Artisan::call('xchange:lifecycle:prepare', [
        '--seed' => true,
    ]);

    enableNetbankTreasuryForTests();

    Artisan::call('xchange:lifecycle:prepare', [
        '--seed' => true,
    ]);

    $system = User::query()->where('email', 'system@example.test')->sole();
    $system->unsetRelation('wallet');
    app()->instance(
        SystemUserResolverContract::class,
        new class($system) implements SystemUserResolverContract
        {
            public function __construct(private readonly User $system) {}

            public function resolve(): Wallet
            {
                return $this->system;
            }
        },
    );
});

it('reuses the system issuer balance without inflating it on repeat', function (): void {
    $issuer = User::query()->where('email', 'system@example.test')->sole();
    $balanceBefore = (float) $issuer->wallet->balanceFloat;

    Artisan::call('xchange:lifecycle:prepare', [
        '--seed' => true,
    ]);

    expect($balanceBefore)->toBeGreaterThan(0.0)
        ->and((float) $issuer->fresh()->wallet->balanceFloat)->toBe($balanceBefore);
});

it('issues and claims an onboarding Voucher through the explicit execution workflow', function (): void {
    config()->set('x-change.commercial.enabled', true);

    expect(config('x-change.lifecycle.scenarios.onboarding_voucher.rider'))->toBe([
        'message' => null,
        'url' => null,
        'splash' => null,
    ]);

    $output = new BufferedOutput;
    $exitCode = Artisan::call('xchange:lifecycle:run', [
        'scenario' => 'onboarding_voucher',
        '--json' => true,
    ], $output);
    $rendered = $output->fetch();
    $payload = json_decode($rendered, true);

    expect($exitCode)->toBe(0, $rendered)
        ->and($payload)->toBeArray()
        ->and($payload['schema'])->toBe('x-change.lifecycle.onboarding-voucher.v2')
        ->and($payload['success'])->toBeTrue()
        ->and(data_get($payload, 'issuer.role'))->toBe('system')
        ->and(data_get($payload, 'issuer.funding_boundary'))->toBe('isolated_compatibility_wallet')
        ->and(data_get($payload, 'issuance_ledger.pay_code_principal_minor'))->toBe(5_000)
        ->and(data_get($payload, 'issuance_ledger.instruction_cost_minor'))->toBeGreaterThan(0)
        ->and(data_get($payload, 'issuance_ledger.instruction_debit_minor'))
        ->toBe(data_get($payload, 'issuance_ledger.instruction_cost_minor'))
        ->and(data_get($payload, 'issuance_ledger.estimated_total_commitment_minor'))
        ->toBe(
            data_get($payload, 'issuance_ledger.pay_code_principal_minor')
            + data_get($payload, 'issuance_ledger.instruction_cost_minor'),
        )
        ->and(data_get($payload, 'issuance_ledger.principal_treatment_at_issuance'))
        ->toBe('voucher_liability_only')
        ->and(data_get($payload, 'voucher.onboarding'))->toBeTrue()
        ->and(data_get($payload, 'voucher.execution_driver'))->toBe('onboarding_account_provisioning')
        ->and(data_get($payload, 'voucher.claimed'))->toBeTrue()
        ->and(data_get($payload, 'recipient_account.mobile_verified'))->toBeTrue()
        ->and(data_get($payload, 'recipient_account.platform_account_ready'))->toBeTrue()
        ->and(data_get($payload, 'recipient_account.treasury_positions_ready'))->toBeTrue()
        ->and(data_get($payload, 'recipient_account.client_funds_minor'))->toBe(0)
        ->and(data_get($payload, 'recipient_account.pay_code_reserve_minor'))->toBe(0)
        ->and(data_get($payload, 'economic_outcome.provider_payout_minor'))->toBe(0)
        ->and(data_get($payload, 'economic_outcome.recipient_client_funds_credit_minor'))->toBe(0)
        ->and(data_get($payload, 'economic_outcome.principal_disposition'))
        ->toBe('redeemed_without_provider_payout_or_account_credit')
        ->and(data_get($payload, 'economic_outcome.requires_product_decision'))->toBeTrue()
        ->and(data_get($payload, 'controls.mobile_verification_required'))->toBeTrue()
        ->and(data_get($payload, 'controls.provider_calls'))->toBeFalse()
        ->and(data_get($payload, 'controls.provider_attempt_count'))->toBe(0)
        ->and(data_get($payload, 'controls.external_payout_suppressed'))->toBeTrue()
        ->and(data_get($payload, 'controls.raw_otp_persisted'))->toBeFalse()
        ->and(data_get($payload, 'controls.canonical_claim_link'))->toStartWith('/x/claim/')
        ->and(config('x-change.commercial.enabled'))->toBeTrue();

    $voucher = Voucher::query()
        ->where('code', data_get($payload, 'voucher.code'))
        ->sole();

    expect($voucher->redeemed_at)->not->toBeNull()
        ->and($voucher->owner?->getAttribute('email'))->toBe('system@example.test')
        ->and(data_get($voucher->metadata, 'instructions.rider.message'))->toBeNull()
        ->and(data_get($voucher->metadata, 'instructions.rider.url'))->toBeNull()
        ->and(data_get($voucher->metadata, 'instructions.rider.splash'))->toBeNull()
        ->and(User::query()->whereIn('mobile', ['09179990001', '639179990001'])->count())
        ->toBe(1)
        ->and(data_get(
            $voucher->redeemers()->latest('id')->first()?->metadata,
            'redemption.inputs.otp.value',
        ))->toBe('verified')
        ->and(json_encode(
            $voucher->redeemers()->latest('id')->first()?->metadata,
        ))->not->toContain('otp_code');
});

it('does not fabricate OTP verification evidence when the onboarding policy disables OTP', function (): void {
    config()->set('x-change.onboarding.voucher.require_otp', false);

    $output = new BufferedOutput;
    $exitCode = Artisan::call('xchange:lifecycle:run', [
        'scenario' => 'onboarding_voucher',
        '--json' => true,
    ], $output);
    $rendered = $output->fetch();
    $payload = json_decode($rendered, true);

    expect($exitCode)->toBe(0, $rendered)
        ->and(data_get($payload, 'controls.mobile_verification_required'))->toBeFalse()
        ->and(data_get($payload, 'recipient_account.mobile_verified'))->toBeFalse()
        ->and(data_get($payload, 'controls.raw_otp_persisted'))->toBeFalse();

    $voucher = Voucher::query()
        ->where('code', data_get($payload, 'voucher.code'))
        ->sole();
    $inputs = (array) data_get(
        $voucher->redeemers()->latest('id')->first()?->metadata,
        'redemption.inputs',
        [],
    );

    expect($inputs)->not->toHaveKeys([
        'verified_at',
        'otp',
        'otp_verified',
        'otp_verification',
    ]);
});

it('accepts explicit claimant identity overrides without changing the system issuer', function (): void {
    config()->set('x-change.onboarding.voucher.require_otp', false);

    $output = new BufferedOutput;
    $exitCode = Artisan::call('xchange:lifecycle:run', [
        'scenario' => 'onboarding_voucher',
        '--claim-mobile' => '09175180722',
        '--claim-name' => 'Lifecycle Payee',
        '--claim-email' => 'lifecycle-payee-09175180722@example.test',
        '--json' => true,
    ], $output);
    $rendered = $output->fetch();
    $payload = json_decode($rendered, true);

    expect($exitCode)->toBe(0, $rendered)
        ->and(data_get($payload, 'issuer.role'))->toBe('system')
        ->and(data_get($payload, 'recipient_account.mobile'))->toEndWith('0722')
        ->and(User::query()
            ->whereIn('mobile', ['09175180722', '639175180722'])
            ->where('name', 'Lifecycle Payee')
            ->where('email', 'lifecycle-payee-09175180722@example.test')
            ->count())->toBe(1);
});
