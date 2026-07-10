<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use Carbon\CarbonImmutable;
use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityRetentionPolicyContract;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityRecordData;

class DefaultCockpitOperatorIssuanceActivityRetentionPolicy implements CockpitOperatorIssuanceActivityRetentionPolicyContract
{
    public function __construct(
        private readonly int $days = 30,
    ) {}

    public function retentionUntil(CockpitOperatorIssuanceActivityRecordData $record): ?string
    {
        if (is_string($record->retention_until) && $record->retention_until !== '') {
            return $record->retention_until;
        }

        if (! is_string($record->occurred_at) || $record->occurred_at === '') {
            return null;
        }

        return CarbonImmutable::parse($record->occurred_at)
            ->addDays($this->days)
            ->toAtomString();
    }

    public function isRetainable(CockpitOperatorIssuanceActivityRecordData $record): bool
    {
        if ($record->activity_id === '') {
            return false;
        }

        foreach ($this->unsafeExposureFlags() as $flag) {
            if (($record->redaction_flags[$flag] ?? false) === true) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<int, string>
     */
    private function unsafeExposureFlags(): array
    {
        return [
            'raw_payloads_exposed',
            'provider_payloads_exposed',
            'wallet_data_exposed',
            'recipient_secrets_exposed',
        ];
    }
}
