<?php

declare(strict_types=1);

use LBHurtado\XChange\Data\DebitData;
use LBHurtado\XChange\Data\IssuerData;
use LBHurtado\XChange\Data\Onboarding\OnboardIssuerResultData;
use LBHurtado\XChange\Data\Onboarding\OpenIssuerWalletResultData;
use LBHurtado\XChange\Data\PayCode\GeneratePayCodeResultData;
use LBHurtado\XChange\Data\PayCodeLinksData;
use LBHurtado\XChange\Data\PricingEstimateData;
use LBHurtado\XChange\Data\WalletData;
use LBHurtado\XChange\Services\ApiResponseFactory;

it('builds a success response with configured envelope keys', function () {
    $factory = new ApiResponseFactory;

    $response = $factory->success([
        'code' => 'TEST-1234',
    ], [
        'trace' => 'abc',
    ], 201);

    expect($response->getStatusCode())->toBe(201);

    $payload = $response->getData(true);

    expect($payload)->toBe([
        'success' => true,
        'data' => [
            'code' => 'TEST-1234',
        ],
        'meta' => [
            'trace' => 'abc',
        ],
    ]);
});

it('builds an error response with configured envelope keys', function () {
    $factory = new ApiResponseFactory;

    $response = $factory->error(
        'Wallet balance is insufficient.',
        'INSUFFICIENT_WALLET_BALANCE',
        [],
        422,
    );

    expect($response->getStatusCode())->toBe(422);

    $payload = $response->getData(true);

    expect($payload)->toBe([
        'success' => false,
        'message' => 'Wallet balance is insufficient.',
        'code' => 'INSUFFICIENT_WALLET_BALANCE',
        'errors' => [],
    ]);
});

it('serializes PricingEstimateData in a success response', function () {
    $factory = new ApiResponseFactory;

    $dto = new PricingEstimateData(
        currency: 'PHP',
        base_fee: 1.0,
        components: [
            'selfie' => 5.0,
            'signature' => 3.0,
        ],
        total: 9.0,
    );

    $response = $factory->success($dto, [], 200);

    expect($response->getStatusCode())->toBe(200);

    $payload = $response->getData(true);
    expect($payload['success'])->toBeTrue();
    expect($payload['meta'])->toBe([]);

    expect($payload['data'])->toMatchArray([
        'currency' => 'PHP',
        'components' => [
            'selfie' => 5,
            'signature' => 3,
        ],
    ]);

    expect((float) data_get($payload, 'data.base_fee'))->toBe(1.0);
    expect((float) data_get($payload, 'data.total'))->toBe(9.0);
});

it('serializes OnboardIssuerResultData in a success response', function () {
    $factory = new ApiResponseFactory;

    $dto = new OnboardIssuerResultData(
        issuer: new IssuerData(
            id: 1,
            name: 'Issuer Name',
            email: 'issuer@example.com',
            mobile: '09171234567',
            country: 'PH',
        ),
    );

    $response = $factory->success($dto, [], 201);

    expect($response->getStatusCode())->toBe(201);

    $payload = $response->getData(true);

    expect($payload)->toBe([
        'success' => true,
        'data' => [
            'issuer' => [
                'id' => 1,
                'name' => 'Issuer Name',
                'email' => 'issuer@example.com',
                'mobile' => '09171234567',
                'country' => 'PH',
            ],
        ],
        'meta' => [],
    ]);
});

it('serializes OpenIssuerWalletResultData in a success response', function () {
    $factory = new ApiResponseFactory;

    $dto = new OpenIssuerWalletResultData(
        issuer: new IssuerData(
            id: 1,
        ),
        wallet: new WalletData(
            id: 10,
            slug: 'platform',
            name: 'Platform Wallet',
            balance: 0,
        ),
    );

    $response = $factory->success($dto, [], 201);

    expect($response->getStatusCode())->toBe(201);

    $payload = $response->getData(true);

    expect($payload)->toBe([
        'success' => true,
        'data' => [
            'issuer' => [
                'id' => 1,
                'name' => null,
                'email' => null,
                'mobile' => null,
                'country' => null,
            ],
            'wallet' => [
                'id' => 10,
                'slug' => 'platform',
                'name' => 'Platform Wallet',
                'balance' => 0,
            ],
        ],
        'meta' => [],
    ]);
});

it('serializes GeneratePayCodeResultData in a success response', function () {
    $factory = new ApiResponseFactory;

    $dto = new GeneratePayCodeResultData(
        voucher_id: 99,
        code: 'TEST-1234',
        amount: 100.0,
        currency: 'PHP',
        issuer: new IssuerData(
            id: 1,
        ),
        cost: new PricingEstimateData(
            currency: 'PHP',
            base_fee: 1.0,
            components: [
                'selfie' => 5.0,
            ],
            total: 6.0,
        ),
        wallet: [
            'balance_before' => 1000.0,
            'balance_after' => 994.0,
        ],
        debit: new DebitData(
            id: 501,
            amount: null,
        ),
        links: new PayCodeLinksData(
            redeem: 'https://example.test/disburse?code=TEST-1234',
            redeem_path: '/disburse?code=TEST-1234',
        ),
    );

    $response = $factory->success($dto, [
        'idempotency' => [
            'key' => null,
            'replayed' => false,
        ],
    ], 201);

    expect($response->getStatusCode())->toBe(201);

    $payload = $response->getData(true);
    expect($payload['success'])->toBeTrue();

    expect($payload)->toHaveKey('data');
    expect($payload)->toHaveKey('meta');

    expect($payload['data'])->toMatchArray([
        'voucher_id' => 99,
        'code' => 'TEST-1234',
        'currency' => 'PHP',
        'debit' => [
            'id' => 501,
            'amount' => null,
        ],
        'links' => [
            'redeem' => 'https://example.test/disburse?code=TEST-1234',
            'redeem_path' => '/disburse?code=TEST-1234',
        ],
        'wallet' => [
            'balance_before' => 1000,
            'balance_after' => 994,
        ],
    ]);

    expect(data_get($payload, 'data.issuer.id'))->toBe(1);
    expect(data_get($payload, 'data.issuer.name'))->toBeNull();
    expect(data_get($payload, 'data.issuer.email'))->toBeNull();
    expect(data_get($payload, 'data.issuer.mobile'))->toBeNull();
    expect(data_get($payload, 'data.issuer.country'))->toBeNull();

    expect(data_get($payload, 'data.cost.currency'))->toBe('PHP');
    expect(data_get($payload, 'data.cost.components.selfie'))->toBe(5);
    expect((float) data_get($payload, 'data.cost.base_fee'))->toBe(1.0);
    expect((float) data_get($payload, 'data.cost.total'))->toBe(6.0);

    expect((float) data_get($payload, 'data.amount'))->toBe(100.0);

    expect((float) data_get($payload, 'data.amount'))->toBe(100.0);
    expect((float) data_get($payload, 'data.cost.base_fee'))->toBe(1.0);
    expect((float) data_get($payload, 'data.cost.total'))->toBe(6.0);

    expect(data_get($payload, 'data.issuer.name'))->toBeNull();
    expect(data_get($payload, 'data.issuer.email'))->toBeNull();
    expect(data_get($payload, 'data.issuer.mobile'))->toBeNull();
    expect(data_get($payload, 'data.issuer.country'))->toBeNull();

    expect($payload['meta'])->toBe([
        'idempotency' => [
            'key' => null,
            'replayed' => false,
        ],
    ]);
});
