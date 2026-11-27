<?php

declare(strict_types=1);

use LBHurtado\XChange\Exceptions\TreasuryConfigurationException;
use LBHurtado\XChange\Services\Configuration\AdditionalTreasuryConnectionConfiguration;

it('decodes named additional Treasury connections without changing their declared mode', function (): void {
    $connections = AdditionalTreasuryConnectionConfiguration::decode(
        '{"netbank-dedicated-secondary":{"provider":"netbank","mode":"required","currency":"PHP","decimal_places":2,"inventory_reference":"inventory:netbank:dedicated:secondary","settlement_resource_reference":"resource:netbank:dedicated:secondary","settlement_resource_type":"cash_at_bank","custody_mode":"provider_projection","required_capabilities":["readiness_probe","balance_read","funding_evidence_read","funding_instruction_issue","settlement_execution"]}}',
        ['netbank-primary', 'paynamics-primary'],
    );

    expect($connections)->toHaveKey('netbank-dedicated-secondary')
        ->and($connections['netbank-dedicated-secondary']['mode'])->toBe('required')
        ->and($connections['netbank-dedicated-secondary']['inventory_reference'])
        ->toBe('inventory:netbank:dedicated:secondary');
});

it('allows an omitted additional connection configuration', function (): void {
    expect(AdditionalTreasuryConnectionConfiguration::decode(null, ['netbank-primary']))->toBe([]);
});

it('fails closed for malformed or unsafe additional Treasury connection configuration', function (
    string $configuration,
    string $message,
): void {
    expect(fn (): array => AdditionalTreasuryConnectionConfiguration::decode(
        $configuration,
        ['netbank-primary', 'paynamics-primary'],
    ))->toThrow(TreasuryConfigurationException::class, $message);
})->with([
    'invalid JSON' => ['{', 'XCHANGE_TREASURY_ADDITIONAL_CONNECTIONS_JSON must contain a JSON object.'],
    'list' => ['[]', 'XCHANGE_TREASURY_ADDITIONAL_CONNECTIONS_JSON must contain a JSON object.'],
    'reserved connection' => ['{"netbank-primary":{}}', 'cannot replace reserved connection [netbank-primary]'],
    'non-object connection' => ['{"netbank-dedicated-secondary":"required"}', 'must be a JSON object'],
]);
