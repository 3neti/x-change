<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Funding;

use LBHurtado\EmiCore\Models\ProviderFundingObservation;

final class StandingFundingRecognitionPolicy
{
    public function accepts(ProviderFundingObservation $observation): bool
    {
        return $this->acceptsStatus($observation->provider_status);
    }

    public function acceptsStatus(string $status): bool
    {
        return in_array(strtolower(trim($status)), $this->creditableStatuses(), true);
    }

    public function isProvisional(ProviderFundingObservation $observation): bool
    {
        return $this->accepts($observation)
            && strtolower(trim($observation->provider_status)) !== 'settled';
    }

    /**
     * @return list<string>
     */
    public function creditableStatuses(): array
    {
        $configured = config(
            'x-change.funding.standing_addresses.creditable_provider_statuses',
            ['settled'],
        );
        $statuses = is_array($configured) ? $configured : [$configured];
        $normalized = array_values(array_unique(array_filter(array_map(
            static fn (mixed $status): string => is_string($status)
                ? strtolower(trim($status))
                : '',
            $statuses,
        ))));

        return in_array('settled', $normalized, true)
            ? $normalized
            : ['settled', ...$normalized];
    }
}
