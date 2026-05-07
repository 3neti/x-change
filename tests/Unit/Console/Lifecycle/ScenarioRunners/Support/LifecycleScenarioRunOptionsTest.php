<?php

declare(strict_types=1);

use LBHurtado\XChange\Lifecycle\Scenarios\LifecycleScenarioRunOptions;

it('builds lifecycle scenario run options from console options', function () {
    $options = LifecycleScenarioRunOptions::fromConsoleOptions([
        'issuer' => '1',
        'wallet' => '2',
        'amount' => '100',
        'timeout' => '180',
        'poll' => '10',
        'max-polls' => '18',
        'only-attempt' => 'success',
        'no-claim' => true,
        'json' => true,
        'accept-pending' => true,
    ]);

    expect($options->issuer)->toBe('1')
        ->and($options->wallet)->toBe('2')
        ->and($options->amount)->toBe('100')
        ->and($options->timeout)->toBe('180')
        ->and($options->poll)->toBe('10')
        ->and($options->maxPolls)->toBe('18')
        ->and($options->onlyAttempt)->toBe('success')
        ->and($options->noClaim)->toBeTrue()
        ->and($options->json)->toBeTrue()
        ->and($options->acceptPending)->toBeTrue();
});

it('normalizes empty string options to null', function () {
    $options = LifecycleScenarioRunOptions::fromConsoleOptions([
        'issuer' => '',
        'wallet' => ' ',
        'amount' => null,
    ]);

    expect($options->issuer)->toBeNull()
        ->and($options->wallet)->toBeNull()
        ->and($options->amount)->toBeNull();
});
