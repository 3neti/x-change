<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use LBHurtado\Voucher\Models\Voucher;
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
});

it('issues and claims an onboarding Voucher through the explicit execution workflow', function (): void {
    $output = new BufferedOutput;
    $exitCode = Artisan::call('xchange:lifecycle:run', [
        'scenario' => 'onboarding_voucher',
        '--json' => true,
    ], $output);
    $rendered = $output->fetch();
    $payload = json_decode($rendered, true);

    expect($exitCode)->toBe(0, $rendered)
        ->and($payload)->toBeArray()
        ->and($payload['schema'])->toBe('x-change.lifecycle.onboarding-voucher.v1')
        ->and($payload['success'])->toBeTrue()
        ->and(data_get($payload, 'voucher.onboarding'))->toBeTrue()
        ->and(data_get($payload, 'voucher.execution_driver'))->toBe('onboarding_account_provisioning')
        ->and(data_get($payload, 'voucher.claimed'))->toBeTrue()
        ->and(data_get($payload, 'recipient_account.mobile_verified'))->toBeTrue()
        ->and(data_get($payload, 'recipient_account.platform_account_ready'))->toBeTrue()
        ->and(data_get($payload, 'controls.provider_calls'))->toBeFalse()
        ->and(data_get($payload, 'controls.raw_otp_persisted'))->toBeFalse()
        ->and(data_get($payload, 'controls.canonical_claim_link'))->toStartWith('/x/claim/');

    $voucher = Voucher::query()
        ->where('code', data_get($payload, 'voucher.code'))
        ->sole();

    expect($voucher->redeemed_at)->not->toBeNull()
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
