<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Treasury;

use LBHurtado\XChange\Data\Treasury\TreasuryProviderConnectionData;
use LBHurtado\XChange\Exceptions\TreasuryConfigurationException;

final readonly class TreasuryOpeningCapitalizationPolicyResolver
{
    public function __construct(
        private TreasuryProviderConnectionCatalog $connections,
    ) {}

    /**
     * @return list<string>
     */
    public function connectionReferences(?string $requestedPolicy = null): array
    {
        $policy = mb_strtolower(trim(
            $requestedPolicy
                ?? (string) config(
                    'x-change.treasury.opening_capitalization.default_policy',
                    'unattributed',
                ),
        ));
        $this->assertPolicy($policy, 'opening capitalization');
        $active = $this->connections->active();

        if ($policy === 'unattributed') {
            return [];
        }

        if ($policy === 'system-capital') {
            return array_map(
                static fn (TreasuryProviderConnectionData $connection): string => $connection->reference,
                $active,
            );
        }

        $configured = (array) config(
            'x-change.treasury.opening_capitalization.connection_policies',
            [],
        );

        return array_values(array_map(
            static fn (TreasuryProviderConnectionData $connection): string => $connection->reference,
            array_filter(
                $active,
                function (TreasuryProviderConnectionData $connection) use ($configured): bool {
                    $connectionPolicy = mb_strtolower(trim((string) (
                        $configured[$connection->reference]
                            ?? 'unattributed'
                    )));
                    $this->assertPolicy(
                        $connectionPolicy,
                        "connection [{$connection->reference}]",
                        allowConfigured: false,
                    );

                    return $connectionPolicy === 'system-capital';
                },
            ),
        ));
    }

    private function assertPolicy(
        string $policy,
        string $context,
        bool $allowConfigured = true,
    ): void {
        $allowed = $allowConfigured
            ? ['unattributed', 'system-capital', 'configured']
            : ['unattributed', 'system-capital'];

        if (! in_array($policy, $allowed, true)) {
            throw new TreasuryConfigurationException(
                "Invalid {$context} policy [{$policy}]. Expected ".implode(
                    ', ',
                    $allowed,
                ).'.',
            );
        }
    }
}
