<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Laravel\Passport\Client;
use LBHurtado\XChange\Actions\PartnerApi\CreatePartnerApiClient;
use LBHurtado\XChange\Enums\PartnerApiClientStatus;
use LBHurtado\XChange\Models\PartnerApiClient;
use LBHurtado\XChange\Tests\Fakes\User;

it('creates a scoped client credentials identity bound to one issuer account', function () {
    $issuer = User::query()->create([
        'name' => 'Saras Issuer',
        'email' => 'saras-issuer@example.test',
        'password' => Hash::make('password'),
    ]);

    $credential = app(CreatePartnerApiClient::class)->handle(
        name: 'Saras AI Sandbox',
        issuer: $issuer,
        environment: 'sandbox',
        scopes: ['capabilities:read', 'pay-codes:estimate', 'pay-codes:issue'],
        mandate: ['maximum_amount_minor' => 50000],
    );

    $partner = PartnerApiClient::query()->where('reference', $credential->reference)->sole();
    $oauthClient = Client::query()->findOrFail($credential->client_id);

    expect($credential->client_secret)->not->toBeEmpty()
        ->and($partner->oauth_client_id)->toBe($oauthClient->getKey())
        ->and($partner->issuer->is($issuer))->toBeTrue()
        ->and($partner->status)->toBe(PartnerApiClientStatus::Active)
        ->and($partner->isActive())->toBeTrue()
        ->and($partner->scopes)->toBe([
            'capabilities:read',
            'pay-codes:estimate',
            'pay-codes:issue',
        ])
        ->and(data_get($partner->mandate, 'maximum_amount_minor'))->toBe(50000)
        ->and(data_get($partner->mandate, 'currencies'))->toBe(['PHP'])
        ->and($oauthClient->grant_types)->toBe(['client_credentials'])
        ->and($oauthClient->secret)->not->toBe($credential->client_secret);
});

it('rejects scopes that are not in the Partner API contract', function () {
    $issuer = User::query()->create([
        'name' => 'Saras Issuer',
        'email' => 'saras-invalid-scope@example.test',
        'password' => Hash::make('password'),
    ]);

    expect(fn () => app(CreatePartnerApiClient::class)->handle(
        name: 'Saras AI Sandbox',
        issuer: $issuer,
        scopes: ['treasury:mutate'],
    ))->toThrow(InvalidArgumentException::class, 'Unknown Partner API scopes');

    expect(PartnerApiClient::query()->count())->toBe(0)
        ->and(Client::query()->count())->toBe(0);
});

it('refuses sandbox credential activation in a production application', function () {
    $issuer = User::query()->create([
        'name' => 'Production Issuer',
        'email' => 'production-sandbox@example.test',
        'password' => Hash::make('password'),
    ]);
    $create = app(CreatePartnerApiClient::class);
    $this->app->detectEnvironment(static fn (): string => 'production');

    try {
        expect(fn () => $create->handle(
            name: 'Unsafe Sandbox Client',
            issuer: $issuer,
        ))->toThrow(InvalidArgumentException::class, 'cannot be activated in a production application');
    } finally {
        $this->app->detectEnvironment(static fn (): string => 'testing');
    }

    expect(PartnerApiClient::query()->count())->toBe(0)
        ->and(Client::query()->count())->toBe(0);
});
