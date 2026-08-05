<?php

declare(strict_types=1);

it('locks a concrete voucher row before calculating the next payout destination revision', function (): void {
    $source = file_get_contents(
        __DIR__.'/../../../src/Actions/Disbursement/RefurbishRejectedPayCodePayout.php',
    );

    expect($source)->toBeString()
        ->and($source)->toContain('->whereKey($voucher->getKey())')
        ->and($source)->toContain('->lockForUpdate()')
        ->and($source)->not->toMatch('/lockForUpdate\(\)\s*->max\(/');
});
