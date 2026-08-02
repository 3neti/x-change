<?php

declare(strict_types=1);

use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use LBHurtado\XChange\Services\Deployment\CloudStagingAcceptance;

it('accepts public and authenticated Cloud entry points without moving money', function (): void {
    Http::fake([
        'https://x-bank.example/*' => Http::response('ready', 200),
        'https://x-bank.example/x/cockpit' => Http::response('', 302, ['Location' => '/login']),
    ]);

    $result = (new CloudStagingAcceptance(app(Factory::class)))
        ->inspect('https://x-bank.example');

    expect($result['success'])->toBeTrue()
        ->and($result['provider_calls'])->toBeFalse()
        ->and($result['real_money_transfer'])->toBeFalse()
        ->and($result['checks'])->toHaveCount(3);
});

it('fails acceptance when a published Vite entry is missing', function (): void {
    Http::fake([
        '*' => Http::response('Unable to locate file in Vite manifest', 500),
    ]);

    $result = (new CloudStagingAcceptance(app(Factory::class)))
        ->inspect('https://x-bank.example');

    expect($result['success'])->toBeFalse();
});
