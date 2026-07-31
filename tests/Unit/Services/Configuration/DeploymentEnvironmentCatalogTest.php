<?php

declare(strict_types=1);

use LBHurtado\EmiCore\Contracts\DeploymentEnvironmentContributor;
use LBHurtado\EmiCore\Data\Configuration\EnvironmentVariableData;
use LBHurtado\XChange\Services\Configuration\DeploymentEnvironmentCatalog;

it('sorts contributed variables and rejects duplicate keys', function (): void {
    $contributor = new class implements DeploymentEnvironmentContributor
    {
        public function environmentVariables(): array
        {
            return [descriptor('Z_KEY'), descriptor('A_KEY')];
        }
    };

    expect(array_column(
        (new DeploymentEnvironmentCatalog([$contributor]))->variables(),
        'key',
    ))->toBe(['A_KEY', 'Z_KEY']);

    expect(fn () => (new DeploymentEnvironmentCatalog([
        $contributor,
        $contributor,
    ]))->variables())->toThrow(RuntimeException::class, 'Duplicate');
});

function descriptor(string $key): EnvironmentVariableData
{
    return new EnvironmentVariableData(
        key: $key,
        description: 'Test variable.',
        category: 'Test',
    );
}
