<?php

declare(strict_types=1);

use LBHurtado\XChange\Lifecycle\Scenarios\LifecycleScenarioRunOptions;

it('builds lifecycle scenario run options from console options', function () {
    $options = LifecycleScenarioRunOptions::fromConsoleOptions([
        'issuer' => '1',
        'maker' => '15',
        'checker' => '16',
        'wallet' => '2',
        'amount' => '100',
        'claim-mobile' => '09175180722',
        'claim-name' => 'Lifecycle Recipient',
        'claim-email' => 'lifecycle-recipient@example.test',
        'timeout' => '180',
        'poll' => '10',
        'max-polls' => '18',
        'only-attempt' => 'success',
        'no-claim' => true,
        'json' => true,
        'accept-pending' => true,
        'live-provider' => true,
    ]);

    expect($options->issuer)->toBe('1')
        ->and($options->maker)->toBe('15')
        ->and($options->checker)->toBe('16')
        ->and($options->wallet)->toBe('2')
        ->and($options->amount)->toBe(100.0)
        ->and($options->claimMobile)->toBe('09175180722')
        ->and($options->claimName)->toBe('Lifecycle Recipient')
        ->and($options->claimEmail)->toBe('lifecycle-recipient@example.test')
        ->and($options->timeout)->toBe(180)
        ->and($options->poll)->toBe(10)
        ->and($options->maxPolls)->toBe(18)
        ->and($options->onlyAttempt)->toBe('success')
        ->and($options->noClaim)->toBeTrue()
        ->and($options->json)->toBeTrue()
        ->and($options->acceptPending)->toBeTrue()
        ->and($options->liveProvider)->toBeTrue();
});

it('normalizes empty string options to null', function () {
    $options = LifecycleScenarioRunOptions::fromConsoleOptions([
        'issuer' => '',
        'maker' => ' ',
        'checker' => null,
        'wallet' => ' ',
        'amount' => null,
    ]);

    expect($options->issuer)->toBeNull()
        ->and($options->maker)->toBeNull()
        ->and($options->checker)->toBeNull()
        ->and($options->wallet)->toBeNull()
        ->and($options->amount)->toBeNull();
});

it('builds commercial actor options from an api payload', function () {
    $options = LifecycleScenarioRunOptions::fromApiPayload([
        'issuer' => 5,
        'maker' => 15,
        'checker' => 16,
    ]);

    expect($options->issuer)->toBe('5')
        ->and($options->maker)->toBe('15')
        ->and($options->checker)->toBe('16');
});
