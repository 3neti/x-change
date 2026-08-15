<?php

declare(strict_types=1);

use LBHurtado\XChange\Exceptions\IdempotencyConflict;
use LBHurtado\XChange\Services\IdempotencyService;

it('returns null when idempotency key has no stored response', function () {
    $service = app(IdempotencyService::class);

    $result = $service->recallOrValidate('abc-123', [
        'cash' => ['amount' => 100],
    ]);

    expect($result)->toBeNull();
});

it('replays stored response when payload fingerprint matches', function () {
    $service = app(IdempotencyService::class);

    $payload = [
        'cash' => ['amount' => 100],
        'inputs' => ['fields' => []],
    ];

    $response = [
        'voucher_id' => 99,
        'code' => 'TEST-1234',
    ];

    $service->remember('abc-123', $payload, $response);

    $recalled = $service->recallOrValidate('abc-123', $payload);

    expect($recalled)->toBe($response);
});

it('throws conflict when the same idempotency key is reused with a different payload', function () {
    $service = app(IdempotencyService::class);

    $service->remember('abc-123', [
        'cash' => ['amount' => 100],
    ], [
        'voucher_id' => 99,
    ]);

    expect(fn () => $service->recallOrValidate('abc-123', [
        'cash' => ['amount' => 200],
    ]))->toThrow(
        IdempotencyConflict::class,
        'The supplied idempotency key has already been used with a different payload.'
    );
});

it('isolates identical idempotency keys across client and operation namespaces', function () {
    $service = app(IdempotencyService::class);
    $payload = ['cash' => ['amount' => 100]];

    $service->remember('shared-key', $payload, ['code' => 'FIRST'], 'partner:first:issue');
    $service->remember('shared-key', $payload, ['code' => 'SECOND'], 'partner:second:issue');
    $service->remember('shared-key', $payload, ['code' => 'CANCELLED'], 'partner:first:cancel');

    expect($service->recallOrValidate('shared-key', $payload, 'partner:first:issue'))
        ->toBe(['code' => 'FIRST'])
        ->and($service->recallOrValidate('shared-key', $payload, 'partner:second:issue'))
        ->toBe(['code' => 'SECOND'])
        ->and($service->recallOrValidate('shared-key', $payload, 'partner:first:cancel'))
        ->toBe(['code' => 'CANCELLED']);
});
