<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Configuration;

use JsonException;
use LBHurtado\XChange\Exceptions\TreasuryConfigurationException;

final class AdditionalTreasuryConnectionConfiguration
{
    /**
     * @param  list<string>  $reservedReferences
     * @return array<string, array<string, mixed>>
     */
    public static function decode(?string $raw, array $reservedReferences): array
    {
        $raw = trim((string) $raw);

        if ($raw === '') {
            return [];
        }

        try {
            $decoded = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new TreasuryConfigurationException(
                'XCHANGE_TREASURY_ADDITIONAL_CONNECTIONS_JSON must contain a JSON object.',
            );
        }

        if (! is_array($decoded) || array_is_list($decoded)) {
            throw new TreasuryConfigurationException(
                'XCHANGE_TREASURY_ADDITIONAL_CONNECTIONS_JSON must contain a JSON object.',
            );
        }

        $connections = [];

        foreach ($decoded as $reference => $configuration) {
            $reference = trim((string) $reference);

            if ($reference === '' || in_array($reference, $reservedReferences, true)) {
                throw new TreasuryConfigurationException(
                    "XCHANGE_TREASURY_ADDITIONAL_CONNECTIONS_JSON cannot replace reserved connection [{$reference}].",
                );
            }

            if (! is_array($configuration) || array_is_list($configuration)) {
                throw new TreasuryConfigurationException(
                    "Additional Treasury connection [{$reference}] must be a JSON object.",
                );
            }

            $connections[$reference] = $configuration;
        }

        return $connections;
    }
}
