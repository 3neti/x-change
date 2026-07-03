<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Support\Cockpit;

use LBHurtado\XChange\Contracts\CockpitRedactorContract;

class DefaultCockpitRedactor implements CockpitRedactorContract
{
    /**
     * @var array<int, string>
     */
    private const DEFAULT_SENSITIVE_KEYS = [
        'account_number',
        'authorization',
        'authorization_code',
        'bank_account',
        'email',
        'mobile',
        'otp',
        'phone',
        'provider_payload',
        'provider_reference',
        'raw_payload',
        'reference_id',
        'secret',
        'token',
        'webhook',
    ];

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $sensitiveKeys
     * @return array<string, mixed>
     */
    public function redact(array $payload, array $sensitiveKeys = []): array
    {
        $sensitive = array_fill_keys([
            ...self::DEFAULT_SENSITIVE_KEYS,
            ...$sensitiveKeys,
        ], true);

        return $this->redactArray($payload, $sensitive);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, bool>  $sensitive
     * @return array<string, mixed>
     */
    private function redactArray(array $payload, array $sensitive): array
    {
        $redacted = [];

        foreach ($payload as $key => $value) {
            if (isset($sensitive[(string) $key])) {
                $redacted[$key] = '[redacted]';

                continue;
            }

            $redacted[$key] = is_array($value)
                ? $this->redactArray($value, $sensitive)
                : $value;
        }

        return $redacted;
    }
}
