<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Notifications\FundsRequestNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use App\Services\DepositQRCode;
use App\Actions\AddFunds;
use Brick\Money\Money;
use App\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    Notification::fake();
});

test('AddFunds action generates QR, stores file and sends notification', function () {
    // Arrange
    $user = User::factory()->create(['email'  => 'test@example.com']);
    $user->mobile = '09171234567';
    $user->save();
    $this->actingAs($user);

    $amount = Money::of(250, 'PHP');
    $base64  = 'data:image/png;base64,' . base64_encode('dummy');

    // Mock the DepositQRCode service
    $service = Mockery::mock(DepositQRCode::class);
    $service->shouldReceive('generate')
        ->once()
        ->with($user, $amount)
        ->andReturn($base64);

    app()->instance(DepositQRCode::class, $service);

    // Act
    $url = AddFunds::run($user, $amount);

    // Assert the QR file was stored
    $path     = parse_url($url, PHP_URL_PATH);
    $relative = ltrim(str_replace('/storage/', '', $path), '/');
    Storage::disk('public')->assertExists($relative);

    // Assert notification was sent with proper payload
    Notification::assertSentTo($user, FundsRequestNotification::class, function ($notification, $channels) use ($user, $url, $amount) {
        expect($channels)->toContain('mail', 'engage_spark');

        $data = $notification->toArray($user);

        return $data['url']      === $url
            && $data['amount']   === (float) $amount->getAmount()->toFloat()
            && $data['currency'] === $amount->getCurrency()->getCurrencyCode();
    });
});
