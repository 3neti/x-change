<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Contracts\AuditLoggerContract;
use LBHurtado\XChange\Enums\FundingRecognitionMode;
use LBHurtado\XChange\Models\StandingFundingAddress;

final class SynchronizeStandingFundingRecognitionMode
{
    public function __construct(
        private readonly AuditLoggerContract $audit,
    ) {}

    public function handle(StandingFundingAddress $address): StandingFundingAddress
    {
        if (! (bool) config(
            'x-change.funding.standing_addresses.enforce_configured_recognition_mode',
            false,
        )) {
            return $address;
        }

        $configured = FundingRecognitionMode::tryFrom((string) config(
            'x-change.funding.standing_addresses.default_recognition_mode',
            FundingRecognitionMode::ObserveOnly->value,
        ));

        if (! $configured instanceof FundingRecognitionMode
            || $address->recognition_mode === $configured) {
            return $address;
        }

        $previous = $address->recognition_mode;
        $synchronized = DB::transaction(function () use ($address, $configured): StandingFundingAddress {
            $locked = StandingFundingAddress::query()
                ->lockForUpdate()
                ->findOrFail($address->getKey());

            if ($locked->recognition_mode === $configured) {
                return $locked;
            }

            $locked->recognition_mode = $configured;
            $locked->version++;
            $locked->metadata = array_merge($locked->metadata ?? [], [
                'recognition_mode_source' => 'enforced_configuration',
            ]);
            $locked->saveQuietly();

            return $locked->refresh();
        }, attempts: 3);

        $this->audit->log('funding.standing_address.recognition_mode_synchronized', [
            'standing_funding_address_reference' => $synchronized->reference,
            'provider' => $synchronized->provider_code,
            'previous_recognition_mode' => $previous->value,
            'recognition_mode' => $synchronized->recognition_mode->value,
        ]);

        return $synchronized;
    }
}
