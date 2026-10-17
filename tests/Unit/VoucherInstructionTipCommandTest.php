<?php

use LBHurtado\XChange\Services\VoucherInstruction\Commands\TipCommand;

it('recognizes the default Tip command range', function (): void {
    $command = new TipCommand(amountPicker: fn (int $minimum, int $maximum): int => 73);

    expect($command->interpret('Tip'))->toBe([
        'amount' => 73.0,
        'minimum' => 50,
        'maximum' => 100,
    ]);
});

it('supports fixed and custom Tip ranges', function (): void {
    $command = new TipCommand(amountPicker: fn (int $minimum, int $maximum): int => $minimum);

    expect($command->interpret('Tip 75'))->toMatchArray([
        'amount' => 75.0,
        'minimum' => 75,
        'maximum' => 75,
    ])->and($command->interpret('Tip 50-100'))->toMatchArray([
        'amount' => 50.0,
        'minimum' => 50,
        'maximum' => 100,
    ]);
});
