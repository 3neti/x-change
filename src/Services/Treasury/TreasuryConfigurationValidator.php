<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Treasury;

use LBHurtado\XChange\Exceptions\TreasuryConfigurationException;

final readonly class TreasuryConfigurationValidator
{
    /** @var array<string, string> */
    private const REQUIRED_CONFIGURATION = [
        'legal_entity_reference' => 'XCHANGE_TREASURY_LEGAL_ENTITY_REFERENCE',
        'principal_reference' => 'XCHANGE_TREASURY_SYSTEM_PRINCIPAL_REFERENCE',
        'system_mandate_reference' => 'XCHANGE_TREASURY_SYSTEM_MANDATE_REFERENCE',
        'legal_profile' => 'XCHANGE_TREASURY_LEGAL_PROFILE',
        'legal_profile_version' => 'XCHANGE_TREASURY_LEGAL_PROFILE_VERSION',
    ];

    public function __construct(
        private TreasuryProviderConnectionCatalog $connections,
    ) {}

    /**
     * @param  list<string>  $connectionReferences
     */
    public function assertConfigured(array $connectionReferences = []): void
    {
        if ($this->connections->active($connectionReferences) === []) {
            return;
        }

        foreach (self::REQUIRED_CONFIGURATION as $key => $environmentKey) {
            if (trim((string) config("x-change.treasury.{$key}")) !== '') {
                continue;
            }

            throw new TreasuryConfigurationException(
                "Treasury configuration [{$key}] is required. "
                ."Set [{$environmentKey}] to a stable deployment identifier, "
                .'run [php artisan optimize:clear], and retry.',
            );
        }
    }
}
