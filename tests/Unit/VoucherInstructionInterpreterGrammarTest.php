<?php

use LBHurtado\XChange\Services\VoucherInstruction\DeterministicVoucherInstructionInterpreter;

it('collects name, age, and address with a claim window', function (): void {
    $result = (new DeterministicVoucherInstructionInterpreter)->interpret(
        'Please get the name, age and address for 1,000 pesos - claimable within 9 AM and 5 PM.',
    );

    expect($result['status'])->toBe('ready')
        ->and($result['instructions']['cash']['amount'])->toBe(1000.0)
        ->and($result['instructions']['inputs']['fields'])->toBe(['name', 'address', 'birth_date'])
        ->and($result['instructions']['validation']['time']['window'])->toMatchArray([
            'start_time' => '09:00',
            'end_time' => '17:00',
            'timezone' => 'Asia/Manila',
        ])
        ->and($result['instructions']['metadata']['custom']['cockpit']['requested_fields'])
        ->toBe(['name', 'age', 'address']);
});

it('resolves The Usual as a named template with its configured default amount', function (): void {
    $result = (new DeterministicVoucherInstructionInterpreter)->interpret('The Usual');

    expect($result['status'])->toBe('ready')
        ->and($result['template_key'])->toBe('the_usual')
        ->and($result['instructions']['cash']['amount'])->toBe(100.0);
});

it('allows a leading amount to override The Usual', function (): void {
    $result = (new DeterministicVoucherInstructionInterpreter)->interpret('5370 The Usual');

    expect($result['status'])->toBe('ready')
        ->and($result['template_key'])->toBe('the_usual')
        ->and($result['instructions']['cash']['amount'])->toBe(5370.0);
});

it('uses defaults for a bare amount', function (): void {
    $result = (new DeterministicVoucherInstructionInterpreter)->interpret('1000');

    expect($result['status'])->toBe('ready')
        ->and($result['instructions']['cash']['amount'])->toBe(1000.0)
        ->and($result['instructions']['inputs']['fields'])->toBe([]);
});

it('treats Tip as a special command and exposes the chosen amount', function (): void {
    $interpreter = new DeterministicVoucherInstructionInterpreter(
        tipAmountPicker: fn (int $minimum, int $maximum): int => 73,
    );
    $result = $interpreter->interpret('Tip');

    expect($result['status'])->toBe('ready')
        ->and($result['command'])->toBe('tip')
        ->and($result['instructions']['cash']['amount'])->toBe(73.0);
});

it('routes a mobile number recipient without treating it as an amount', function (): void {
    $result = (new DeterministicVoucherInstructionInterpreter)->interpret('09467438575 1000');

    expect($result['status'])->toBe('ready')
        ->and($result['instructions']['cash']['amount'])->toBe(1000.0)
        ->and($result['instructions']['cash']['validation']['mobile'])->toBe('09467438575');
});

it('resolves a unique contact name and refuses ambiguous contacts', function (): void {
    $interpreter = new DeterministicVoucherInstructionInterpreter(
        contactResolver: fn (string $name): ?array => $name === 'Sofia'
            ? ['mobile' => '09170000000']
            : null,
    );

    $resolved = $interpreter->interpret('Pay Sofia 400');
    $ambiguous = (new DeterministicVoucherInstructionInterpreter)->interpret('Pay Sofia 400');

    expect($resolved['status'])->toBe('ready')
        ->and($resolved['instructions']['cash']['validation']['mobile'])->toBe('09170000000')
        ->and($ambiguous['status'])->toBe('needs_clarification')
        ->and($ambiguous['instructions'])->toBeNull()
        ->and($ambiguous['unresolved'])->toContain('contact');
});

it('returns no voucher instructions when the prompt is not safely interpretable', function (): void {
    $result = (new DeterministicVoucherInstructionInterpreter)->interpret('Make it special');

    expect($result['status'])->toBe('needs_clarification')
        ->and($result['instructions'])->toBeNull()
        ->and($result['unresolved'])->toContain('amount');
});
