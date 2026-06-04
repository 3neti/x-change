<?php

use LBHurtado\XChange\Actions\Claim\InjectPreparedCompiledClaimInputs;
use LBHurtado\XChange\Data\PreparedCompiledClaimData;

it('injects prepared compiled claim inputs into state', function () {
    $prepared = new PreparedCompiledClaimData(
        code: 'TEST123',
        voucherId: 123,
        inputs: [
            'first_name' => 'Lester',
            'email' => 'lester@example.com',
        ],
    );

    $state = app(InjectPreparedCompiledClaimInputs::class)->handle($prepared);

    expect($state)->toBe([
        'code' => 'TEST123',
        'voucher_id' => 123,
        'inputs' => [
            'first_name' => 'Lester',
            'email' => 'lester@example.com',
        ],
    ]);
});

it('preserves existing state while injecting prepared compiled claim inputs', function () {
    $prepared = new PreparedCompiledClaimData(
        code: 'TEST123',
        voucherId: 123,
        inputs: [
            'first_name' => 'Lester',
        ],
    );

    $state = app(InjectPreparedCompiledClaimInputs::class)->handle($prepared, [
        'flow_id' => 'flow-123',
        'current_step' => 'confirmation',
    ]);

    expect($state)->toMatchArray([
        'flow_id' => 'flow-123',
        'current_step' => 'confirmation',
        'code' => 'TEST123',
        'voucher_id' => 123,
        'inputs' => [
            'first_name' => 'Lester',
        ],
    ]);
});
