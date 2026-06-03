<?php

use LBHurtado\XChange\Data\PreparedCompiledClaimData;

it('creates prepared compiled claim data from session payload', function () {
    $data = PreparedCompiledClaimData::fromSessionPayload([
        'code' => ' test123 ',
        'voucher_id' => 123,
        'inputs' => [
            'first_name' => 'Lester',
        ],
    ]);

    expect($data)->not->toBeNull()
        ->and($data->code)->toBe('TEST123')
        ->and($data->voucherId)->toBe(123)
        ->and($data->inputs)->toBe([
            'first_name' => 'Lester',
        ])
        ->and($data->toArray())->toBe([
            'code' => 'TEST123',
            'voucher_id' => 123,
            'inputs' => [
                'first_name' => 'Lester',
            ],
        ]);
});

it('returns null for malformed prepared compiled claim payload', function () {
    expect(PreparedCompiledClaimData::fromSessionPayload([
        'code' => 'TEST123',
        'voucher_id' => 123,
        'inputs' => 'not-array',
    ]))->toBeNull();
});

it('rejects prepared compiled claim payload without code', function () {
    expect(PreparedCompiledClaimData::fromSessionPayload([
        'voucher_id' => 123,
        'inputs' => [],
    ]))->toBeNull();
});

it('rejects prepared compiled claim payload without voucher id', function () {
    expect(PreparedCompiledClaimData::fromSessionPayload([
        'code' => 'TEST123',
        'inputs' => [],
    ]))->toBeNull();
});

it('rejects prepared compiled claim payload without inputs', function () {
    expect(PreparedCompiledClaimData::fromSessionPayload([
        'code' => 'TEST123',
        'voucher_id' => 123,
    ]))->toBeNull();
});

it('rejects prepared compiled claim payload with non-string code', function () {
    expect(PreparedCompiledClaimData::fromSessionPayload([
        'code' => 123,
        'voucher_id' => 123,
        'inputs' => [],
    ]))->toBeNull();
});

it('rejects prepared compiled claim payload with non-array inputs', function () {
    expect(PreparedCompiledClaimData::fromSessionPayload([
        'code' => 'TEST123',
        'voucher_id' => 123,
        'inputs' => 'not-array',
    ]))->toBeNull();
});
