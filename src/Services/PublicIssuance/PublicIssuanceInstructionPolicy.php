<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\PublicIssuance;

use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

final readonly class PublicIssuanceInstructionPolicy
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function assertAllowed(array $payload): void
    {
        $errors = [];
        $amount = data_get($payload, 'cash.amount');
        $amountString = is_int($amount) || is_float($amount) || is_string($amount)
            ? (string) $amount
            : '';
        $minimum = max(1, (int) config('x-change.public_auto_generate.minimum_principal_minor', 100));
        $maximum = max($minimum, (int) config('x-change.public_auto_generate.maximum_principal_minor', 100_000));

        if (preg_match('/^\d{1,7}(?:\.\d{1,2})?$/', $amountString) !== 1) {
            $errors['cash.amount'][] = 'Enter an amount with at most two decimal places.';
        } else {
            [$whole, $fraction] = array_pad(explode('.', $amountString, 2), 2, '');
            $amountMinor = ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');

            if ($amountMinor < $minimum || $amountMinor > $maximum) {
                $errors['cash.amount'][] = "The amount must be between {$minimum} and {$maximum} minor currency units.";
            }
        }

        $currency = strtoupper(trim((string) data_get($payload, 'cash.currency', '')));

        if (! in_array($currency, (array) config('x-change.public_auto_generate.currencies', ['PHP']), true)) {
            $errors['cash.currency'][] = 'The selected currency is not available for public issuance.';
        }

        foreach (['execution', 'slice_plan', 'metadata.collection_wallet_id', 'metadata.campaign',
            'metadata.slices', 'metadata.slice_policy', 'metadata.custom.settlement',
            'metadata.custom.external_reference', 'metadata.issuer_id', 'issuer_id',
            'prefix', 'mask', 'starts_at', 'expires_at', 'ttl', 'target_amount',
            'rules', 'cash.settlement_rail', 'cash.slice_mode', 'cash.slices',
            'cash.max_slices', 'cash.min_withdrawal', 'cash.mandates',
        ] as $field) {
            if (data_get($payload, $field) !== null) {
                $errors[$field][] = 'This instruction is unavailable for public issuance.';
            }
        }

        if (! in_array(data_get($payload, 'provider'), [null, 'netbank'], true)) {
            $errors['provider'][] = 'The public payment provider is selected by the server.';
        }

        if (! in_array(data_get($payload, 'cash.fee_strategy'), [null, 'absorb'], true)) {
            $errors['cash.fee_strategy'][] = 'The public fee strategy is fixed.';
        }

        if (! in_array(data_get($payload, 'cash.type'), [null, 'default'], true)) {
            $errors['cash.type'][] = 'This cash type is unavailable for public issuance.';
        }

        if (! in_array(data_get($payload, 'claim.default_outcome'), [null, 'provider_disbursement'], true)) {
            $errors['claim.default_outcome'][] = 'This claim outcome is unavailable for public issuance.';
        }

        foreach ((array) data_get($payload, 'claim.outcomes', []) as $outcome) {
            if (data_get($outcome, 'key') !== 'provider_disbursement') {
                $errors['claim.outcomes'][] = 'This claim outcome is unavailable for public issuance.';
                break;
            }
        }

        if (! in_array(data_get($payload, 'metadata.custom.cockpit.template_key'), [null, 'blank-pay-code'], true)) {
            $errors['metadata.custom.cockpit.template_key'][] = 'Public issuance starts from the blank Pay Code.';
        }

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

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function canonicalize(array $payload): array
    {
        $cash = (array) data_get($payload, 'cash', []);
        $metadata = (array) data_get($payload, 'metadata.custom.cockpit.payee', []);

        return [
            'cash' => array_replace(Arr::only($cash, ['amount', 'currency', 'validation']), [
                'fee_strategy' => 'absorb',
                'type' => 'default',
            ]),
            'inputs' => Arr::only((array) data_get($payload, 'inputs', []), ['fields', 'requirements']),
            'feedback' => Arr::only((array) data_get($payload, 'feedback', []), ['email', 'mobile']),
            'rider' => Arr::only((array) data_get($payload, 'rider', []), [
                'message', 'message_format', 'url', 'redirect_timeout', 'splash',
                'splash_format', 'splash_timeout', 'splash_meta', 'og_source', 'stamp',
            ]),
            'count' => 1,
            'provider' => 'netbank',
            'validation' => (array) data_get($payload, 'validation', []),
            'claim' => [
                'outcomes' => [['key' => 'provider_disbursement']],
                'selection' => 'server',
                'consumption' => 'one_of',
                'default_outcome' => 'provider_disbursement',
                'onboarding' => ['mode' => 'if_required'],
                'claimant' => ['mode' => 'unbound'],
                'profile' => 'voucher.claim.v1',
            ],
            'metadata' => [
                'authorization' => 'public_auto_generate',
                'custom' => [
                    'cockpit' => [
                        'template_key' => 'blank-pay-code',
                        'source' => 'public.auto-generate',
                        'builder' => 'guided-voucher-instruction-builder',
                        'payee' => Arr::only($metadata, ['kind', 'explicit_secret']),
                    ],
                ],
            ],
            ...Arr::only($payload, ['_pricing']),
        ];
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
