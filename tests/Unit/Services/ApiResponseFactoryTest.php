<?php

declare(strict_types=1);

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
