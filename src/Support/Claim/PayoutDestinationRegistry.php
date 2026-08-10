<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Support\Claim;

final class PayoutDestinationRegistry
{
    /**
     * @return array{
     *     bank_code: string|null,
     *     bank_name: string|null,
     *     bank_label: string|null,
     *     provider_icon_key: string|null,
     *     settlement_rail: string|null,
     *     account_number_masked: string|null,
     *     route: list<string>
     * }
     */
    public function snapshot(
        mixed $bankCode,
        mixed $accountNumber = null,
        mixed $settlementRail = null,
    ): array {
        $bankCode = $this->nullableString($bankCode);
        $accountNumber = $this->nullableString($accountNumber);
        $settlementRail = $this->nullableString($settlementRail)
            ?? (string) config('x-change.claim.destination.default_settlement_rail', 'INSTAPAY');
        $institution = $this->institution($bankCode);
        $label = $institution['short_label'];

        return [
            'bank_code' => $bankCode,
            'bank_name' => $institution['label'],
            'bank_label' => $label,
            'provider_icon_key' => $institution['icon_key'],
            'settlement_rail' => $settlementRail,
            'account_number_masked' => $this->maskAccountNumber($accountNumber),
            'route' => array_values(array_filter([
                'x-change',
                'NetBank',
                $this->railLabel($settlementRail),
                $label,
                $this->maskAccountNumber($accountNumber),
            ])),
        ];
    }

    /**
     * @return array{label: string|null, short_label: string|null, category: string|null, icon_key: string|null}
     */
    public function institution(?string $bankCode): array
    {
        if ($bankCode === null || $bankCode === '') {
            return [
                'label' => null,
                'short_label' => null,
                'category' => null,
                'icon_key' => null,
            ];
        }

        $configured = (array) config("x-change.claim.destination.institutions.{$bankCode}", []);
        $label = $this->nullableString($configured['label'] ?? null) ?? $bankCode;

        return [
            'label' => $label,
            'short_label' => $this->nullableString($configured['short_label'] ?? null) ?? $label,
            'category' => $this->nullableString($configured['category'] ?? null),
            'icon_key' => $this->nullableString($configured['icon_key'] ?? null) ?? 'institution.generic',
        ];
    }

    public function defaultBankCode(): ?string
    {
        return $this->nullableString(config('x-change.claim.destination.default_bank_code'));
    }

    public function defaultSettlementRail(): string
    {
        return $this->nullableString(config('x-change.claim.destination.default_settlement_rail')) ?? 'INSTAPAY';
    }

    public function railLabel(?string $settlementRail): ?string
    {
        return match ($settlementRail) {
            'INSTAPAY' => 'InstaPay',
            'PESONET' => 'PESONet',
            null, '' => null,
            default => $settlementRail,
        };
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function maskAccountNumber(?string $accountNumber): ?string
    {
        if ($accountNumber === null || $accountNumber === '') {
            return null;
        }

        $suffix = substr($accountNumber, -4);

        return str_repeat('*', max(strlen($accountNumber) - 4, 0)).$suffix;
    }
}
