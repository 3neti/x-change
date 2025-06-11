<?php

use App\Services\DepositQRCode;
use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;
use Brick\Money\Money;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('generates and caches a deposit QR code', function () {
    // Arrange
    Cache::flush();

    $gateway = Mockery::mock(PaymentGatewayInterface::class);
    $user = User::factory()->create();
    $user->mobile = '09171234567';
    $user->save();
    $amount = Money::of(500, 'PHP');

    // Expect the gateway generate method to be called once
    $gateway->shouldReceive('generate')
        ->once()
        ->with($user->mobile, $amount)
        ->andReturn('data:image/png;base64,fake_qr_code');

    $service = new DepositQRCode($gateway);

    // Act - first call hits gateway
    $result1 = $service->generate($user, $amount);
    // Act - second call hits cache
    $result2 = $service->generate($user, $amount);

    // Assert
    expect($result1)->toBe('data:image/png;base64,fake_qr_code');
    expect($result2)->toBe('data:image/png;base64,fake_qr_code');
});
