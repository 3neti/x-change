<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use LBHurtado\XChange\Actions\PayCode\GeneratePayCode;
use LBHurtado\XChange\Tests\Fakes\User as FakeLifecycleUser;

beforeEach(function () {
    config([
        'x-change.lifecycle.defaults.user_model' => FakeLifecycleUser::class,
        'x-change.onboarding.issuer_model' => FakeLifecycleUser::class,
    ]);

    Artisan::call('xchange:lifecycle:prepare', [
        '--seed' => true,
    ]);
});

it('does not debit target amount when generating collectible pay code', function () {
    $issuer = actingAsTestUser(1_000_000);

    $wallet = $issuer->wallet;
    $balanceBefore = (float) $wallet->balanceFloat;

    $result = app(GeneratePayCode::class)->handle([
        'issuer_id' => $issuer->id,

        'cash' => [
            'amount' => 100.00,
            'currency' => 'PHP',
            'settlement_rail' => 'INSTAPAY',
            'validation' => [
                'country' => 'PH',
            ],
        ],

        'inputs' => [
            'fields' => [],
        ],

        'feedback' => [
            'email' => 'example@example.com',
            'mobile' => '09171234567',
            'webhook' => 'https://example.com/webhook',
        ],

        'rider' => [
            'message' => null,
            'url' => null,
            'redirect_timeout' => null,
            'splash' => null,
            'splash_timeout' => null,
            'og_source' => null,
        ],

        'count' => 1,
        'prefix' => 'PAY',
        'mask' => '****',
        'ttl' => null,

        'metadata' => [
            'flow_type' => 'collectible',
        ],
    ]);

    $balanceAfter = (float) $wallet->fresh()->balanceFloat;
    $debited = $balanceBefore - $balanceAfter;

    expect($result->code)->not->toBeEmpty();

    expect($debited)->toBeLessThan(100.00);
});
