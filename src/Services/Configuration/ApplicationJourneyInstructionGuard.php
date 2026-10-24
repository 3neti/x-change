<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Configuration;

use Illuminate\Validation\ValidationException;

final readonly class ApplicationJourneyInstructionGuard
{
    public function __construct(private ApplicationProfileCompiler $profiles) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function validateAndCompile(array $payload, bool $publicIssuance): array
    {
        $journey = data_get($payload, 'metadata.custom.app_journey');
        if ($journey === null) {
            return $payload;
        }

        if (! is_array($journey) || $publicIssuance) {
            throw ValidationException::withMessages(['metadata.custom.app_journey' => 'This journey is unavailable.']);
        }

        $key = $journey['key'] ?? null;
        $labels = [
            'loan_disbursement' => 'Loan disbursement',
            'exchange_payout' => 'Exchange payout',
            'send_local_remittance' => 'Local remittance',
            'send_offshore_remittance' => 'Offshore remittance',
        ];
        if (! is_string($key) || ! isset($labels[$key])
            || ! collect($this->profiles->fromConfig()->navigation)->contains('key', $key)) {
            throw ValidationException::withMessages(['metadata.custom.app_journey.key' => 'This journey is unavailable in the active app profile.']);
        }

        $reference = $journey['reference'] ?? null;
        if (! is_string($reference) || trim($reference) === '' || mb_strlen($reference) > 120
            || preg_match('/[\x00-\x1F\x7F]/', $reference) === 1) {
            throw ValidationException::withMessages(['metadata.custom.app_journey.reference' => 'A bounded journey reference is required.']);
        }

        if ((float) data_get($payload, 'cash.amount', 0) <= 0
            || data_get($payload, 'cash.currency', 'PHP') !== 'PHP'
            || (int) data_get($payload, 'count', 1) !== 1
            || data_get($payload, 'voucher_type', 'redeemable') !== 'redeemable'
            || data_get($payload, 'claim.default_outcome') !== 'provider_disbursement') {
            throw ValidationException::withMessages(['metadata.custom.app_journey' => 'This journey requires one disbursable Pay Code with a positive PHP amount.']);
        }

        if ($key === 'send_offshore_remittance'
            && (! is_string($journey['origin_country'] ?? null)
                || preg_match('/^[A-Z]{2}$/D', $journey['origin_country']) !== 1)) {
            throw ValidationException::withMessages(['metadata.custom.app_journey.origin_country' => 'A two-letter origin country is required.']);
        }

        if ($key === 'exchange_payout'
            && (! is_string($journey['source_currency'] ?? null)
                || preg_match('/^[A-Z]{3}$/D', $journey['source_currency']) !== 1
                || ! is_string($journey['source_amount'] ?? null)
                || preg_match('/^[0-9]{1,15}(?:\.[0-9]{1,8})?$/D', $journey['source_amount']) !== 1
                || (float) $journey['source_amount'] <= 0)) {
            throw ValidationException::withMessages(['metadata.custom.app_journey.source_amount' => 'An external quote source currency and positive amount are required.']);
        }

        if (! filled(data_get($payload, 'rider.message'))) {
            data_set($payload, 'rider.message', $labels[$key].' '.trim($reference));
        }

        return $payload;
    }
}
