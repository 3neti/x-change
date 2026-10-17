<?php

use LBHurtado\XChange\Services\VoucherInstruction\DeterministicVoucherInstructionInterpreter;

it('turns a plain language prompt into a normalized voucher draft', function (): void {
    $result = (new DeterministicVoucherInstructionInterpreter)->interpret(
        '500 pesos. Require signature and selfie. Expires in 1 day.',
    );

    expect($result['status'])->toBe('ready')
        ->and($result['instructions']['cash']['amount'])->toBe(500.0)
        ->and($result['instructions']['inputs']['fields'])->toBe(['signature', 'selfie'])
        ->and($result['instructions']['ttl'])->toBe('P1D')
        ->and($result['parser_version'])->toBe('deterministic-v1');
});

it('asks for an amount instead of guessing one', function (): void {
    $result = (new DeterministicVoucherInstructionInterpreter)->interpret('Require a selfie.');

    expect($result['status'])->toBe('needs_clarification')
        ->and($result['unresolved'])->toBe(['amount']);
});
