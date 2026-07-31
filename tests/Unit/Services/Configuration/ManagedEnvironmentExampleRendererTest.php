<?php

declare(strict_types=1);

use LBHurtado\EmiCore\Data\Configuration\EnvironmentVariableData;
use LBHurtado\XChange\Services\Configuration\ManagedEnvironmentExampleRenderer;

it('adds and replaces only the managed environment example block', function (): void {
    $renderer = new ManagedEnvironmentExampleRenderer;
    $variables = [
        new EnvironmentVariableData(
            key: 'XCHANGE_DEPLOYMENT_PROFILE',
            description: 'Deployment profile.',
            category: 'X-Change',
            configPath: 'x-change.deployment.profile',
            safeExample: 'development',
            required: true,
        ),
        new EnvironmentVariableData(
            key: 'BANK_SECRET',
            description: 'Bank credential.',
            category: 'Provider',
            configPath: 'bank.secret',
            secret: true,
            requiredForProviders: ['bank'],
        ),
    ];

    $first = $renderer->render("APP_NAME=Host\n", $variables, 'custom', ['bank']);
    $second = $renderer->render($first, $variables, 'custom', ['bank']);

    expect($first)->toBe($second)
        ->and($first)->toContain('APP_NAME=Host')
        ->toContain('XCHANGE_DEPLOYMENT_PROFILE=custom')
        ->toContain('BANK_SECRET=')
        ->toContain('Bank credential. Required for this profile.')
        ->not->toContain('real-looking-secret')
        ->and(substr_count($first, ManagedEnvironmentExampleRenderer::BeginMarker))->toBe(1);
});

it('does not duplicate environment keys owned by the host', function (): void {
    $renderer = new ManagedEnvironmentExampleRenderer;
    $variables = [
        new EnvironmentVariableData(
            key: 'QUEUE_CONNECTION',
            description: 'Queue connection.',
            category: 'Runtime',
            configPath: 'queue.default',
            safeExample: 'database',
        ),
        new EnvironmentVariableData(
            key: 'XCHANGE_DEPLOYMENT_PROFILE',
            description: 'Deployment profile.',
            category: 'X-Change',
            configPath: 'x-change.deployment.profile',
            safeExample: 'development',
            required: true,
        ),
    ];

    $first = $renderer->render("QUEUE_CONNECTION=redis\n", $variables, 'netbank', []);
    $second = $renderer->render($first, $variables, 'netbank', []);

    expect($second)->toBe($first)
        ->and(substr_count($second, 'QUEUE_CONNECTION='))->toBe(1)
        ->and($second)->toContain('QUEUE_CONNECTION=redis')
        ->and($second)->toContain('XCHANGE_DEPLOYMENT_PROFILE=netbank');
});
