<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\PublicIssuance;

use Illuminate\Validation\ValidationException;

final readonly class PublicIssuanceInput
{
    /**
     * @param  array<string, mixed>  $input
     * @return array{amount_minor: int, currency: string}
     */
    public function validate(array $input): array
    {
        $currencies = (array) config('x-change.public_auto_generate.currencies', ['PHP']);
        $currency = strtoupper(trim((string) ($input['currency'] ?? 'PHP')));
        $amountMinor = filter_var($input['amount_minor'] ?? null, FILTER_VALIDATE_INT);
        $minimum = max(1, (int) config('x-change.public_auto_generate.minimum_principal_minor', 100));
        $maximum = max($minimum, (int) config('x-change.public_auto_generate.maximum_principal_minor', 100_000));
        $errors = [];

        if ($amountMinor === false || $amountMinor < $minimum || $amountMinor > $maximum) {
            $errors['amount_minor'][] = "The amount must be between {$minimum} and {$maximum} minor currency units.";
        }

        if (! in_array($currency, $currencies, true)) {
            $errors['currency'][] = 'The selected currency is not available for public issuance.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return ['amount_minor' => $amountMinor, 'currency' => $currency];
    }

    /**
     * @return array{amount: string, currency: string}|null
     */
    public function prefill(mixed $amount, mixed $currency): ?array
    {
        if (! is_string($amount) || preg_match('/^\d{1,7}(?:\.\d{1,2})?$/', $amount) !== 1) {
            return null;
        }

        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        $minor = ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');

        try {
            $validated = $this->validate([
                'amount_minor' => $minor,
                'currency' => is_string($currency) ? $currency : 'PHP',
            ]);
        } catch (ValidationException) {
            return null;
        }

        return [
            'amount' => number_format($validated['amount_minor'] / 100, 2, '.', ''),
            'currency' => $validated['currency'],
        ];
    }
}
