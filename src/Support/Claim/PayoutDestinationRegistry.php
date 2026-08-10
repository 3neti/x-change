<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Support\Claim;

use LBHurtado\MoneyIssuer\Contracts\MoneyIssuerDirectoryContract;
use LBHurtado\MoneyIssuer\Data\MoneyIssuerData;

final class PayoutDestinationRegistry
{
    public function __construct(
        private readonly MoneyIssuerDirectoryContract $institutions,
        private readonly PayoutDestinationIconCatalog $iconCatalog = new PayoutDestinationIconCatalog,
    ) {
    }

    /**
     * @return array{
     *     bank_code: string|null,
     *     bank_name: string|null,
     *     bank_label: string|null,
     *     provider_icon_key: string|null,
     *     icon_asset: string|null,
     *     settlement_rail: string|null,
     *     account_number_masked: string|null,
     *     route: list<string>,
     *     route_icons: list<string|null>
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
        $provider = 'NetBank';
        $maskedAccountNumber = $this->maskAccountNumber($accountNumber);

        return [
            'bank_code' => $bankCode,
            'bank_name' => $institution['label'],
            'bank_label' => $label,
            'provider_icon_key' => $institution['icon_key'],
            'icon_asset' => $institution['icon_asset'],
            'settlement_rail' => $settlementRail,
            'account_number_masked' => $maskedAccountNumber,
            'route' => array_values(array_filter([
                'x-change',
                $provider,
                $this->railLabel($settlementRail),
                $label,
                $maskedAccountNumber,
            ])),
            'route_icons' => [
                $this->iconCatalog->orchestratorIconAsset(),
                $this->iconCatalog->iconAssetForProvider($provider),
                $this->iconCatalog->iconAssetForRail($settlementRail),
                $institution['icon_asset'],
            ],
        ];
    }

    /**
     * @return array{label: string|null, short_label: string|null, category: string|null, icon_key: string|null, icon_asset: string|null}
     */
    public function institution(?string $bankCode): array
    {
        if ($bankCode === null || $bankCode === '') {
            return [
                'label' => null,
                'short_label' => null,
                'category' => null,
                'icon_key' => null,
                'icon_asset' => null,
            ];
        }

        $configured = (array) config("x-change.claim.destination.institutions.{$bankCode}", []);

        if ($configured !== []) {
            $label = $this->nullableString($configured['label'] ?? null) ?? $bankCode;

            return [
                'label' => $label,
                'short_label' => $this->nullableString($configured['short_label'] ?? null) ?? $label,
                'category' => $this->nullableString($configured['category'] ?? null),
                'icon_key' => $this->nullableString($configured['icon_key'] ?? null) ?? 'institution.generic',
                'icon_asset' => $this->iconCatalog->iconAssetForCode($bankCode),
            ];
        }

        $resolved = $this->institutions->findInstitutionByBankCode($bankCode);

        if ($resolved instanceof MoneyIssuerData) {
            $label = $this->nullableString($resolved->name) ?? $bankCode;

            return [
                'label' => $label,
                'short_label' => $label,
                'category' => $this->normalizeCategory($resolved->category),
                'icon_key' => 'institution.generic',
                'icon_asset' => $this->iconCatalog->iconAssetForCode($bankCode),
            ];
        }

        // Genuinely unknown code: safe raw-code fallback.
        return [
            'label' => $bankCode,
            'short_label' => $bankCode,
            'category' => null,
            'icon_key' => 'institution.generic',
            'icon_asset' => $this->iconCatalog->iconAssetForCode($bankCode),
        ];
    }

    private function normalizeCategory(string $category): string
    {
        return match ($category) {
            'wallet', 'e_wallet', 'emi' => 'wallet',
            default => 'bank',
        };
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
