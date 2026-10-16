<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\IssuanceBatch;

use Illuminate\Support\Str;
use LBHurtado\XChange\Support\Money\MajorCurrencyAmount;
use Throwable;

final class IssuanceBatchImportNormalizer
{
    /** @var array<string, array<int, string>> */
    private const ALIASES = [
        'name' => ['name', 'beneficiary', 'beneficiary name', 'recipient', 'recipient name', 'full name'],
        'mobile' => ['mobile', 'mobile number', 'phone', 'phone number', 'cellphone', 'cellphone number'],
        'email' => ['email', 'email address'],
        'amount' => ['amount', 'value', 'php', 'salary', 'gross amount', 'benefit amount'],
        'amount_minor' => ['amount minor', 'centavos'],
        'message' => ['message', 'rider message', 'note', 'notes'],
        'external_reference' => ['reference', 'external reference', 'employee id', 'employee number', 'recipient id'],
    ];

    /** @param array<int, string> $headers @return array<string, string> */
    public function detectMapping(array $headers): array
    {
        $mapping = [];
        foreach ($headers as $header) {
            $normalized = $this->normalizedHeader($header);
            foreach (self::ALIASES as $canonical => $aliases) {
                if (! isset($mapping[$canonical]) && in_array($normalized, $aliases, true)) {
                    $mapping[$canonical] = $header;
                }
            }
        }

        return $mapping;
    }

    /** @param array<int, array<string, string>> $rows @param array<string, string> $mapping @return array{items: array<int, array<string, mixed>>, total_minor: int, errors: array<int, string>} */
    public function normalize(array $rows, array $mapping): array
    {
        $items = [];
        $errors = [];
        foreach ($rows as $index => $source) {
            $rowErrors = [];
            $amount = $this->amountMinor($source, $mapping, $rowErrors);
            $name = $this->value($source, $mapping, 'name');
            $mobile = $this->value($source, $mapping, 'mobile');
            $email = $this->value($source, $mapping, 'email');
            if ($name === null) {
                $rowErrors[] = 'Name is required.';
            }
            if ($mobile === null && $email === null) {
                $rowErrors[] = 'A mobile number or email address is required.';
            }
            if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $rowErrors[] = 'Email address is invalid.';
            }
            if ($amount < 1) {
                $rowErrors[] = 'Amount must be positive.';
            }
            $item = [
                'source_row' => $index + 2,
                'status' => $rowErrors === [] ? 'valid' : 'invalid',
                'errors' => $rowErrors,
                'amount_minor' => $amount,
                'currency' => 'PHP',
                'payload' => array_filter([
                    'name' => $name,
                    'mobile' => $mobile,
                    'email' => $email,
                    'message' => $this->value($source, $mapping, 'message'),
                    'external_reference' => $this->value($source, $mapping, 'external_reference'),
                ], static fn (?string $value): bool => $value !== null),
            ];
            $items[] = $item;
            if ($rowErrors !== []) {
                $errors[] = sprintf('Row %d: %s', $index + 2, implode(' ', $rowErrors));
            }
        }

        return ['items' => $items, 'total_minor' => array_sum(array_column(array_filter($items, fn (array $item): bool => $item['status'] === 'valid'), 'amount_minor')), 'errors' => $errors];
    }

    /** @param array<string, string> $source @param array<string, string> $mapping @param array<int, string> $errors */
    private function amountMinor(array $source, array $mapping, array &$errors): int
    {
        try {
            if (isset($mapping['amount_minor'])) {
                $value = $this->value($source, $mapping, 'amount_minor');
                if ($value === null || preg_match('/^\d+$/', $value) !== 1) {
                    throw new \InvalidArgumentException('Amount minor must be a positive whole number.');
                }

                return (int) $value;
            }
            $value = $this->value($source, $mapping, 'amount');
            if ($value === null) {
                throw new \InvalidArgumentException('Amount is required.');
            }

            return MajorCurrencyAmount::toMinor($value);
        } catch (Throwable $exception) {
            $errors[] = $exception->getMessage();

            return 0;
        }
    }

    /** @param array<string, string> $source @param array<string, string> $mapping */
    private function value(array $source, array $mapping, string $key): ?string
    {
        $header = $mapping[$key] ?? null;
        $value = is_string($header) ? trim((string) ($source[$header] ?? '')) : '';

        return $value === '' ? null : $value;
    }

    private function normalizedHeader(string $header): string
    {
        return Str::of($header)->lower()->replace(['_', '-', '.'], ' ')->squish()->value();
    }
}
