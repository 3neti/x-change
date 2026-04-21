<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\EventStoreContract;
use LBHurtado\XChange\Contracts\IdempotencyStoreContract;
use LBHurtado\XChange\Services\EventLifecycleService;

it('lists events from the event store', function () {
    $events = [
        [
            'id' => 'evt-001',
            'type' => 'voucher.created',
            'status' => 'recorded',
            'resource_type' => 'voucher',
            'resource_id' => '99',
            'occurred_at' => '2026-04-22T10:00:00+00:00',
        ],
        [
            'id' => 'evt-002',
            'type' => 'voucher.cancelled',
            'status' => 'recorded',
            'resource_type' => 'voucher',
            'resource_id' => '100',
            'occurred_at' => '2026-04-22T10:05:00+00:00',
        ],
    ];

    $store = Mockery::mock(EventStoreContract::class);
    $store->shouldReceive('list')
        ->once()
        ->with([])
        ->andReturn($events);

    $idempotency = Mockery::mock(IdempotencyStoreContract::class);

    $service = new EventLifecycleService($store, $idempotency);

    $result = $service->list([]);

    expect($result)->toBeArray()
        ->and($result)->toHaveCount(2)
        ->and($result[0]['id'])->toBe('evt-001')
        ->and($result[0]['type'])->toBe('voucher.created')
        ->and($result[1]['id'])->toBe('evt-002');
});

it('shows a single event from the event store', function () {
    $event = [
        'id' => 'evt-001',
        'type' => 'voucher.created',
        'status' => 'recorded',
        'actor' => 'system',
        'resource_type' => 'voucher',
        'resource_id' => '99',
        'correlation_id' => 'corr-001',
        'idempotency_key' => 'idem-001',
        'occurred_at' => '2026-04-22T10:00:00+00:00',
        'payload' => [
            'voucher_code' => 'TEST-1234',
        ],
    ];

    $store = Mockery::mock(EventStoreContract::class);
    $store->shouldReceive('find')
        ->once()
        ->with('evt-001')
        ->andReturn($event);

    $idempotency = Mockery::mock(IdempotencyStoreContract::class);

    $service = new EventLifecycleService($store, $idempotency);

    $result = $service->show('evt-001');

    expect($result)->toBeArray()
        ->and($result['id'])->toBe('evt-001')
        ->and($result['type'])->toBe('voucher.created')
        ->and($result['status'])->toBe('recorded')
        ->and($result['payload']['voucher_code'])->toBe('TEST-1234');
});

it('returns a missing event placeholder when event is not found', function () {
    $store = Mockery::mock(EventStoreContract::class);
    $store->shouldReceive('find')
        ->once()
        ->with('evt-missing')
        ->andReturn(null);

    $idempotency = Mockery::mock(IdempotencyStoreContract::class);

    $service = new EventLifecycleService($store, $idempotency);

    $result = $service->show('evt-missing');

    expect($result)->toBeArray()
        ->and($result['id'])->toBe('evt-missing')
        ->and($result['type'])->toBe('event.unknown')
        ->and($result['status'])->toBe('missing')
        ->and($result['payload'])->toBeArray();
});

it('shows idempotency key details from the idempotency store', function () {
    $record = [
        'replayed' => true,
        'first_seen_at' => '2026-04-22T10:00:00+00:00',
        'last_seen_at' => '2026-04-22T10:05:00+00:00',
        'request_fingerprint' => 'fp-001',
        'response_status' => 200,
    ];

    $store = Mockery::mock(EventStoreContract::class);
    $idempotency = Mockery::mock(IdempotencyStoreContract::class);

    $idempotency->shouldReceive('find')
        ->once()
        ->with('idem-001')
        ->andReturn($record);

    $service = new EventLifecycleService($store, $idempotency);

    $result = $service->showIdempotencyKey('idem-001');

    expect($result)->toBeArray()
        ->and($result['key'])->toBe('idem-001')
        ->and($result['replayed'])->toBeTrue()
        ->and($result['first_seen_at'])->toBe('2026-04-22T10:00:00+00:00')
        ->and($result['last_seen_at'])->toBe('2026-04-22T10:05:00+00:00')
        ->and($result['request_fingerprint'])->toBe('fp-001')
        ->and($result['response_status'])->toBe(200);
});

it('returns a default idempotency record when key is not found', function () {
    $store = Mockery::mock(EventStoreContract::class);
    $idempotency = Mockery::mock(IdempotencyStoreContract::class);

    $idempotency->shouldReceive('find')
        ->once()
        ->with('idem-missing')
        ->andReturn(null);

    $service = new EventLifecycleService($store, $idempotency);

    $result = $service->showIdempotencyKey('idem-missing');

    expect($result)->toBeArray()
        ->and($result['key'])->toBe('idem-missing')
        ->and($result['replayed'])->toBeFalse()
        ->and($result['first_seen_at'])->toBeNull()
        ->and($result['last_seen_at'])->toBeNull()
        ->and($result['request_fingerprint'])->toBeNull()
        ->and($result['response_status'])->toBeNull();
});

it('passes filters through when listing events', function () {
    $filters = [
        'type' => 'voucher.created',
        'resource_type' => 'voucher',
    ];

    $store = Mockery::mock(EventStoreContract::class);
    $store->shouldReceive('list')
        ->once()
        ->with($filters)
        ->andReturn([]);

    $idempotency = Mockery::mock(IdempotencyStoreContract::class);

    $service = new EventLifecycleService($store, $idempotency);

    $result = $service->list($filters);

    expect($result)->toBe([]);
});
