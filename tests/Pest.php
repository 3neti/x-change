<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use LBHurtado\Voucher\Actions\GenerateVouchers;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\XChange\Tests\Fakes\FakePayoutProvider;
use LBHurtado\XChange\Tests\Fakes\User;
use LBHurtado\XChange\Tests\TestCase;
use Bavix\Wallet\Interfaces\Wallet;
use Illuminate\Support\Str;

uses(TestCase::class)->in('Unit', 'Feature');

afterEach(function () {
    Mockery::close();
});

function actingAsTestUser(int $amount = 1_000_000): User
{
    $user = User::query()->create([
        'name' => 'Test User',
        'email' => 'tester+'.Str::uuid().'@example.com',
        'password' => Hash::make('password'),
    ]);

    test()->actingAs($user);

    fundTestUserWallet($user, $amount);

    return $user;
}

function fundTestUserWallet(User $user, int $amount = 1_000_000): void
{
    if (! $user instanceof Wallet) {
        throw new RuntimeException('Test user must implement Bavix Wallet interface.');
    }

    $wallet = $user->wallet()->firstOrCreate([
        'slug' => 'platform',
    ], [
        'name' => 'Platform Wallet',
    ]);

    if ((int) $wallet->balance < $amount) {
        $wallet->deposit($amount - (int) $wallet->balance);
    }
}
function validVoucherInstructions(
    float $amount = 100.00,
    ?string $settlementRail = 'INSTAPAY',
    array $overrides = []
): VoucherInstructionsData {
    $data = array_replace_recursive([
        'cash' => [
            'amount' => $amount,
            'currency' => 'PHP',
            'settlement_rail' => $settlementRail,
            'validation' => [
                'secret' => null,
                'mobile' => null,
                'payable' => null,
                'country' => 'PH',
                'location' => null,
                'radius' => null,
            ],
        ],
        'inputs' => [
            'fields' => [],
        ],
        'feedback' => [
            'email' => 'example@example.com',
            'mobile' => '09171234567',
            'webhook' => 'http://example.com/webhook',
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
        'prefix' => 'TEST',
        'mask' => '****',
        'ttl' => null,
        'metadata' => [
            'issuer_id' => (string) optional(auth()->user())->id,
            'issuer_name' => optional(auth()->user())->name,
            'issuer_email' => optional(auth()->user())->email,
            'created_at' => now()->toIso8601String(),
            'issued_at' => now()->toIso8601String(),
        ],
    ], $overrides);

    return VoucherInstructionsData::from($data);
}

function issueVoucher(
    VoucherInstructionsData|array|null $instructions = null
) {
    if (! auth()->check()) {
        actingAsTestUser();
    }

    /** @var User $user */
    $user = auth()->user();

    fundTestUserWallet($user);

    $instructions ??= validVoucherInstructions();

    if (is_array($instructions)) {
        $instructions = VoucherInstructionsData::from($instructions);
    }

    return GenerateVouchers::run($instructions)->first();
}

function fakePayoutProvider(): FakePayoutProvider
{
    /** @var TestCase $test */
    $test = test();

    return $test->fakePayoutProvider()->reset();
}
