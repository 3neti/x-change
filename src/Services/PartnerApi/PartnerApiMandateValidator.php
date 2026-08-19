<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\PartnerApi;

use InvalidArgumentException;

final class PartnerApiMandateValidator
{
    /**
     * @param  list<string>  $scopes
     * @param  array<string, mixed>  $mandate
     */
    public function validate(array $scopes, array $mandate): void
    {
        $unknownScopes = array_values(array_diff(
            $scopes,
            array_keys((array) config('x-change.partner_api.scopes', [])),
        ));

        if ($unknownScopes !== []) {
            throw new InvalidArgumentException('Unknown Partner API scopes: '.implode(', ', $unknownScopes));
        }

        if (! array_intersect($scopes, ['stored-value:read', 'stored-value:spend'])) {
            return;
        }

        if (data_get($mandate, 'stored_value_spend.enabled') !== true) {
            throw new InvalidArgumentException(
                'Stored-value scopes require an explicitly enabled stored-value spend mandate.',
            );
        }

        $currencies = array_values(array_unique(array_map(
            static fn (mixed $currency): string => strtoupper(trim((string) $currency)),
            (array) data_get($mandate, 'stored_value_spend.currencies', []),
        )));

        if ($currencies === [] || in_array('', $currencies, true)) {
            throw new InvalidArgumentException(
                'Stored-value scopes require at least one explicit currency.',
            );
        }

        if (in_array('stored-value:spend', $scopes, true)) {
            $perSpend = (int) data_get($mandate, 'stored_value_spend.maximum_amount_minor', 0);
            $daily = (int) data_get($mandate, 'stored_value_spend.daily_amount_minor', 0);

            if ($perSpend <= 0 || $daily < $perSpend) {
                throw new InvalidArgumentException(
                    'Stored-value spend limits must be positive and the daily limit must cover one maximum spend.',
                );
            }
        }
    }
}
