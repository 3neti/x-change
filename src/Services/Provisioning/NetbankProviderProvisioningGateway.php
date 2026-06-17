<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Provisioning;

use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;
use LBHurtado\XChange\Contracts\ProviderProvisioningGatewayContract;
use LBHurtado\XChange\Contracts\WalletProvisioningContract;
use LBHurtado\XChange\Enums\ProviderProvisioningMode;
use RuntimeException;

class NetbankProviderProvisioningGateway implements ProviderProvisioningGatewayContract
{
    public function __construct(
        protected WalletProvisioningContract $walletProvisioning,
        protected ?PaymentGatewayInterface $gateway = null,
    ) {}

    public function supports(string $provider, string $mode): bool
    {
        if (strtolower($provider) !== 'netbank') {
            return false;
        }

        return in_array($mode, [
            ProviderProvisioningMode::LedgerWallet->value,
            ProviderProvisioningMode::BankAccountLink->value,
        ], true);
    }

    public function provision(mixed $owner, array $payload): array
    {
        $provider = 'netbank';
        $mode = (string) data_get($payload, 'mode', ProviderProvisioningMode::BankAccountLink->value);
        $topology = (string) data_get($payload, 'topology', 'ledger_pooled');

        if (! $this->supports($provider, $mode)) {
            throw new RuntimeException("NetBank provisioning mode [{$mode}] is not supported.");
        }

        $wallet = null;

        if ($mode === ProviderProvisioningMode::LedgerWallet->value) {
            $wallet = $this->ensureLocalWallet($owner, $payload);
        }

        $bankCode = $this->bankCode($payload);
        $maskedAccountNumber = $this->maskedAccountNumber($payload);
        $bankAccountReady = $mode !== ProviderProvisioningMode::BankAccountLink->value
            || ($bankCode !== null && $maskedAccountNumber !== null);

        $status = match ($mode) {
            ProviderProvisioningMode::LedgerWallet->value => $wallet !== null ? 'ready' : 'pending',
            ProviderProvisioningMode::BankAccountLink->value => $bankAccountReady ? 'ready' : 'pending',
            default => 'pending',
        };

        return [
            'provider' => $provider,
            'topology' => $topology,
            'purpose' => data_get($payload, 'purpose'),
            'mode' => $mode,
            'status' => $status,
            'provider_account_id' => null,
            'provider_wallet_id' => null,
            'provider_bank_account_id' => $bankAccountReady
                ? $this->providerBankAccountId($bankCode, $maskedAccountNumber)
                : null,
            'external_uid' => $this->externalUid($owner, $mode),
            'verification_status' => $status === 'ready' ? 'APPROVED' : 'PENDING',
            'capabilities' => [
                'issue_pay_code' => $mode === ProviderProvisioningMode::LedgerWallet->value
                    ? $status === 'ready'
                    : false,
                'redeem_pay_code' => $mode === ProviderProvisioningMode::BankAccountLink->value
                    ? $status === 'ready'
                    : false,
            ],
            'metadata' => array_filter([
                'wallet' => $wallet !== null ? [
                    'id' => is_object($wallet) ? ($wallet->id ?? null) : null,
                    'slug' => is_object($wallet) ? ($wallet->slug ?? null) : null,
                ] : null,
                'bank_code' => $bankCode,
                'account_number_masked' => $maskedAccountNumber,
                'source_account' => $this->sourceAccountReadiness(),
            ], static fn (mixed $value): bool => $value !== null),
        ];
    }

    public function refresh(mixed $link): array
    {
        return [
            'status' => data_get($link, 'status', 'ready'),
            'refreshed' => true,
            'source_account' => $this->sourceAccountReadiness(),
        ];
    }

    protected function ensureLocalWallet(mixed $owner, array $payload): mixed
    {
        return $this->walletProvisioning->open($owner, [
            'wallet' => [
                'slug' => data_get(
                    $payload,
                    'wallet.slug',
                    config('x-change.onboarding.default_wallet_slug', 'platform'),
                ),
                'name' => data_get(
                    $payload,
                    'wallet.name',
                    config('x-change.onboarding.default_wallet_name', 'Platform Wallet'),
                ),
            ],
        ]);
    }

    protected function bankCode(array $payload): ?string
    {
        $bankCode = data_get($payload, 'bank_code')
            ?? data_get($payload, 'bank_account.bank_code');

        return is_string($bankCode) && trim($bankCode) !== ''
            ? trim($bankCode)
            : null;
    }

    protected function maskedAccountNumber(array $payload): ?string
    {
        $masked = data_get($payload, 'account_number_masked');

        if (is_string($masked) && trim($masked) !== '') {
            return trim($masked);
        }

        $accountNumber = data_get($payload, 'account_number')
            ?? data_get($payload, 'bank_account.account_number');

        if (! is_string($accountNumber) || trim($accountNumber) === '') {
            return null;
        }

        $digits = trim($accountNumber);
        $length = strlen($digits);

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', $length - 4).substr($digits, -4);
    }

    protected function providerBankAccountId(?string $bankCode, ?string $maskedAccountNumber): ?string
    {
        if ($bankCode === null || $maskedAccountNumber === null) {
            return null;
        }

        return sprintf('NETBANK-%s-%s', $bankCode, str_replace('*', 'X', $maskedAccountNumber));
    }

    protected function externalUid(mixed $owner, string $mode): string
    {
        $ownerKey = is_object($owner) && method_exists($owner, 'getKey')
            ? (string) $owner->getKey()
            : 'owner';

        return sprintf('netbank-%s-%s', $ownerKey, $mode);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function sourceAccountReadiness(): ?array
    {
        if (! (bool) config('x-change.provider_runtime.providers.netbank.source_account_readiness.enabled', false)) {
            return null;
        }

        $accountNumber = config('x-change.provider_runtime.providers.netbank.source_account_readiness.account_number')
            ?: config('disbursement.account_number');

        if (! is_string($accountNumber) || trim($accountNumber) === '') {
            return [
                'ready' => false,
                'checked' => false,
                'message' => 'No NetBank source account number configured for readiness checks.',
            ];
        }

        try {
            if (! $this->gateway instanceof PaymentGatewayInterface) {
                return [
                    'ready' => false,
                    'checked' => false,
                    'account_number_masked' => $this->maskAccountNumber(trim($accountNumber)),
                    'message' => 'NetBank source-account readiness check is enabled, but no payment gateway is bound.',
                ];
            }

            $balance = $this->gateway->checkAccountBalance(trim($accountNumber));

            return [
                'ready' => true,
                'checked' => true,
                'account_number_masked' => $this->maskAccountNumber(trim($accountNumber)),
                'balance' => $balance['balance'] ?? null,
                'available_balance' => $balance['available_balance'] ?? null,
                'currency' => $balance['currency'] ?? null,
            ];
        } catch (\Throwable $e) {
            return [
                'ready' => false,
                'checked' => true,
                'account_number_masked' => $this->maskAccountNumber(trim($accountNumber)),
                'message' => $e->getMessage(),
            ];
        }
    }

    protected function maskAccountNumber(string $accountNumber): string
    {
        $length = strlen($accountNumber);

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', $length - 4).substr($accountNumber, -4);
    }
}
