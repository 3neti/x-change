<?php

declare(strict_types=1);

use LBHurtado\XChange\Services\Configuration\CoreDeploymentEnvironmentContributor;

function environmentKeysFrom(string $contents): array
{
    preg_match_all('/^([A-Z][A-Z0-9_]*)=/m', $contents, $matches);

    $keys = array_values(array_unique($matches[1] ?? []));
    sort($keys);

    return $keys;
}

it('keeps the canonical package example and merge stub aligned with descriptors', function (): void {
    $packageExample = file_get_contents(dirname(__DIR__, 2).'/.env.example');
    $mergeStub = file_get_contents(
        dirname(__DIR__, 2).'/stubs/environment/x-change.env.example.stub',
    );
    $descriptorKeys = array_map(
        static fn ($variable): string => $variable->key,
        (new CoreDeploymentEnvironmentContributor)->environmentVariables(),
    );
    sort($descriptorKeys);

    expect(environmentKeysFrom($packageExample))->toBe($descriptorKeys)
        ->and(environmentKeysFrom($mergeStub))->toBe($descriptorKeys);
});

it('ships the shared UI primitives required by published form-flow pages', function () {
    $packageRoot = dirname(__DIR__, 2);

    expect($packageRoot.'/resources/js/components/x-change-shared-alert-dialog/index.ts')
        ->toBeFile()
        ->and($packageRoot.'/resources/js/components/x-change-shared-textarea/index.ts')
        ->toBeFile();
});

it('keeps known secret examples empty', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/.env.example');
    $variables = (new CoreDeploymentEnvironmentContributor)->environmentVariables();

    foreach ($variables as $variable) {
        if (! $variable->secret) {
            continue;
        }

        expect($contents)->toMatch('/^'.preg_quote($variable->key, '/').'=$/m');
    }
});

it('uses the standard section order', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/.env.example');
    $sections = [
        'Activation And Profile',
        'Identity And Authorization',
        'Instruction Services',
        'Evidence Storage',
        'Treasury And Accounting',
        'Delivery',
        'Queues And Scheduling',
        'Broadcasting',
    ];
    $positions = array_map(
        static fn (string $section): int|false => mb_strpos($contents, $section),
        $sections,
    );
    $sortedPositions = $positions;
    sort($sortedPositions);

    expect($positions)->not->toContain(false)
        ->and($positions)->toBe($sortedPositions);
});

it('documents the temporary System Readiness visibility flag without commissioning it', function (): void {
    $variable = collect((new CoreDeploymentEnvironmentContributor)->environmentVariables())
        ->firstWhere('key', 'XCHANGE_COCKPIT_SYSTEM_READINESS_VISIBLE');

    expect($variable)->not->toBeNull()
        ->and($variable->configPath)->toBeNull()
        ->and($variable->safeExample)->toBe('false')
        ->and(file_get_contents(dirname(__DIR__, 2).'/config/x-change.php'))
        ->toContain("'XCHANGE_COCKPIT_SYSTEM_READINESS_VISIBLE',\n                false,");
});
