<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Campaigns;

use Illuminate\Support\Str;
use LBHurtado\EmiCore\Enums\SettlementRail;
use LBHurtado\MoneyIssuer\Contracts\InstitutionResolverContract;
use LBHurtado\XChange\Support\Money\MajorCurrencyAmount;
use Throwable;

final readonly class CampaignWorksheetImportNormalizer
{
    /** @var array<string, array<int, string>> */
    private const HEADER_ALIASES = [
        'name' => ['name', 'beneficiary', 'beneficiary name', 'recipient', 'recipient name', 'full name'],
        'mobile' => ['mobile', 'mobile number', 'phone', 'phone number', 'cellphone', 'cellphone number'],
        'email' => ['email', 'email address'],
        'amount' => ['amount', 'value', 'php', 'salary', 'gross amount', 'benefit amount'],
        'amount_minor' => ['amount minor', 'centavos'],
        'institution' => ['bank', 'bank name', 'institution', 'financial institution', 'wallet', 'wallet provider'],
        'bank_account' => ['bank account', 'bank account number', 'account', 'account number', 'destination account'],
        'remarks' => ['remarks', 'remark', 'notes', 'note', 'message'],
        'external_reference' => ['reference', 'external reference', 'employee id', 'employee number', 'recipient id'],
        'delivery_preference' => ['delivery', 'delivery preference', 'channel'],
    ];

    public function __construct(private InstitutionResolverContract $institutions) {}

    /**
     * @param  array<int, string>  $headers
     * @return array<string, string>
     */
    public function detectMapping(array $headers): array
    {
        $mapping = [];

        foreach ($headers as $header) {
            $normalizedHeader = $this->normalizedHeader($header);
            foreach (self::HEADER_ALIASES as $canonical => $aliases) {
                if (! isset($mapping[$canonical]) && in_array($normalizedHeader, $aliases, true)) {
                    $mapping[$canonical] = $header;
                }
            }
        }

        return $mapping;
    }

    /**
     * @param  array<int, array<string, string>>  $rows
     * @param  array<string, string>  $mapping
     * @return array<int, array<string, mixed>>
     */
    public function normalizeRows(
        array $rows,
        array $mapping,
        string $fulfillmentMode,
        string $defaultWallet = 'GCash',
        string $defaultDeliveryPreference = 'manual',
    ): array {
        return array_map(function (array $source, int $index) use (
            $mapping,
            $fulfillmentMode,
            $defaultWallet,
            $defaultDeliveryPreference,
        ): array {
            $errors = [];
            $amountMinor = $this->amountMinor($source, $mapping, $errors);
            $delivery = mb_strtolower($this->value($source, $mapping, 'delivery_preference') ?? $defaultDeliveryPreference);
            if (! in_array($delivery, ['manual', 'sms', 'email'], true)) {
                $errors[] = 'Delivery must be Manual, SMS, or Email.';
            }

            $beneficiary = array_filter([
                'name' => $this->value($source, $mapping, 'name'),
                'mobile' => $this->value($source, $mapping, 'mobile'),
                'email' => $this->value($source, $mapping, 'email'),
                'remarks' => $this->value($source, $mapping, 'remarks'),
                'external_reference' => $this->value($source, $mapping, 'external_reference'),
            ], fn (?string $value): bool => $value !== null);

            if (isset($beneficiary['email']) && filter_var($beneficiary['email'], FILTER_VALIDATE_EMAIL) === false) {
                $errors[] = 'Email address is invalid.';
            }

            if ($fulfillmentMode === 'direct_bank_transfer') {
                $this->normalizeBankDestination(
                    $source,
                    $mapping,
                    $beneficiary,
                    $amountMinor,
                    $defaultWallet,
                    $errors,
                );
            } elseif (! isset($beneficiary['mobile']) && ! isset($beneficiary['email'])) {
                $errors[] = 'A mobile number or email address is required for Pay Code distribution.';
            }

            $normalized = [
                'beneficiary' => $beneficiary,
                'amount_minor' => $amountMinor,
                'currency' => 'PHP',
                'delivery_preference' => in_array($delivery, ['manual', 'sms', 'email'], true) ? $delivery : 'manual',
            ];

            return [
                'source_row' => $index + 2,
                'status' => $errors === [] ? 'valid' : 'invalid',
                'source' => $source,
                'normalized' => $errors === [] ? $normalized : null,
                'errors' => $errors,
            ];
        }, $rows, array_keys($rows));
    }

    /**
     * @param  array<string, string>  $source
     * @param  array<string, string>  $mapping
     * @param  array<string, string>  $beneficiary
     * @param  array<int, string>  $errors
     */
    private function normalizeBankDestination(
        array $source,
        array $mapping,
        array &$beneficiary,
        int $amountMinor,
        string $defaultWallet,
        array &$errors,
    ): void {
        $mobile = $beneficiary['mobile'] ?? null;
        $bankAccount = $this->value($source, $mapping, 'bank_account');
        $institution = $this->value($source, $mapping, 'institution');

        if ($bankAccount === null && $mobile !== null) {
            $bankAccount = $mobile;
            $institution ??= $defaultWallet;
        }

        if ($bankAccount === null) {
            $errors[] = 'A mobile number or bank account is required for direct transfer.';

            return;
        }

        if ($institution === null) {
            $errors[] = 'A bank or wallet name is required for direct transfer.';

            return;
        }

        $rail = $amountMinor < 5_000_000 ? SettlementRail::INSTAPAY : SettlementRail::PESONET;

        try {
            $resolved = $this->institutions->resolve($institution, $rail);
            $beneficiary['bank_account'] = $bankAccount;
            $beneficiary['bank_code'] = $resolved->bankCode;
            $beneficiary['institution'] = $resolved->displayName;
            $beneficiary['settlement_rail'] = $rail->value;
        } catch (Throwable $exception) {
            $errors[] = $exception->getMessage();
        }
    }

    /**
     * @param  array<string, string>  $source
     * @param  array<string, string>  $mapping
     * @param  array<int, string>  $errors
     */
    private function amountMinor(array $source, array $mapping, array &$errors): int
    {
        try {
            if (isset($mapping['amount_minor'])) {
                $value = $this->value($source, $mapping, 'amount_minor');
                if ($value === null || preg_match('/^\d+$/', $value) !== 1 || (int) $value < 1) {
                    throw new \InvalidArgumentException('Amount in centavos must be a positive whole number.');
                }

                return (int) $value;
            }

            $value = $this->value($source, $mapping, 'amount');
            if ($value === null) {
                throw new \InvalidArgumentException('Amount in pesos is required.');
            }

            return MajorCurrencyAmount::toMinor($value);
        } catch (Throwable $exception) {
            $errors[] = $exception->getMessage();

            return 0;
        }
    }

    /** @param array<string, string> $source @param array<string, string> $mapping */
    private function value(array $source, array $mapping, string $canonical): ?string
    {
        $header = $mapping[$canonical] ?? null;
        $value = is_string($header) ? trim((string) ($source[$header] ?? '')) : '';

        return $value === '' ? null : $value;
    }

    private function normalizedHeader(string $header): string
    {
        return Str::of($header)->lower()->replace(['_', '-', '.'], ' ')->squish()->value();
    }
}
