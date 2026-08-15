<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use LBHurtado\XChange\Models\PartnerApiClient;
use LBHurtado\XChange\Tests\Fakes\User;

it('provisions a scoped client and emits its secret once as JSON', function () {
    $issuer = User::query()->create([
        'name' => 'Saras Issuer',
        'email' => 'saras-command@example.test',
        'password' => Hash::make('password'),
    ]);

    $exitCode = Artisan::call('x-change:partner-api:client', [
        'name' => 'Saras AI Sandbox',
        '--issuer' => $issuer->email,
        '--scope' => ['capabilities:read', 'pay-codes:issue'],
        '--maximum-amount-minor' => '50000',
        '--rail' => ['INSTAPAY'],
        '--json' => true,
    ]);

    $output = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($exitCode)->toBe(0)
        ->and(data_get($output, 'schema'))->toBe('x-change.partner-api-credential.v1')
        ->and(data_get($output, 'client_id'))->not->toBeEmpty()
        ->and(data_get($output, 'client_secret'))->not->toBeEmpty()
        ->and(data_get($output, 'secret_display'))->toBe('one_time_only')
        ->and(PartnerApiClient::query()->where('issuer_id', (string) $issuer->getKey())->exists())->toBeTrue();
});

it('requires explicit confirmation for production credentials', function () {
    $issuer = User::query()->create([
        'name' => 'Production Issuer',
        'email' => 'production-command@example.test',
        'password' => Hash::make('password'),
    ]);

    $this->artisan('x-change:partner-api:client', [
        'name' => 'Production Client',
        '--issuer' => $issuer->email,
        '--environment' => 'production',
        '--json' => true,
    ])->assertFailed();

    expect(PartnerApiClient::query()->count())->toBe(0);
});
