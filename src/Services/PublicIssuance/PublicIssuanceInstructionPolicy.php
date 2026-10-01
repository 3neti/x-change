<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\PublicIssuance;

use Illuminate\Validation\ValidationException;

final readonly class PublicIssuanceInstructionPolicy
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function assertAllowed(array $payload): void
    {
        $errors = [];

        if ($this->filled(data_get($payload, 'feedback.webhook'))) {
            $errors['feedback.webhook'][] = 'Webhook destinations are unavailable for public issuance.';
        }

        if ((bool) data_get($payload, 'onboarding', false)) {
            $errors['onboarding'][] = 'Invitation issuance is unavailable on the public surface.';
        }

        if (in_array(data_get($payload, 'voucher_type'), ['payable', 'settlement'], true)) {
            $errors['voucher_type'][] = 'Collection and settlement Pay Codes require an x-change Account.';
        }

        if ((bool) data_get($payload, 'stored_value.enabled', false)) {
            $errors['stored_value'][] = 'Stored-value Pay Codes require an x-change Account.';
        }

        if ((int) data_get($payload, 'count', 1) !== 1) {
            $errors['count'][] = 'Public issuance creates one Pay Code at a time.';
        }

        $destination = trim((string) data_get($payload, 'rider.url', ''));

        if ($destination !== '' && ! $this->isSafeDestination($destination)) {
            $errors['rider.url'][] = 'Use a public HTTPS destination without embedded credentials or a private network address.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function filled(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }

    private function isSafeDestination(string $destination): bool
    {
        $parts = parse_url($destination);

        if (! is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || ! isset($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])) {
            return false;
        }

        $host = strtolower(rtrim((string) $parts['host'], '.'));

        if ($host === 'localhost'
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.test')) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) === false) {
            return true;
        }

        return filter_var(
            $host,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }
}
