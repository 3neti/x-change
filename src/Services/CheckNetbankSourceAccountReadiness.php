<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;
use Throwable;

class CheckNetbankSourceAccountReadiness
{
    public function __construct(
        protected ?PaymentGatewayInterface $gateway = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(?int $requiredMinor = null): array
    {
        $accountNumber = $this->sourceAccountNumber();

        if (! $this->enabled()) {
            return [
                'enabled' => false,
                'ready' => true,
                'checked' => false,
                'account_number' => $accountNumber,
                'account_number_masked' => $accountNumber !== null ? $this->maskAccountNumber($accountNumber) : null,
                'message' => 'NetBank source-account readiness check is disabled.',
            ];
        }

        if ($accountNumber === null) {
            return [
                'enabled' => true,
                'ready' => false,
                'checked' => false,
                'message' => 'No NetBank source account number is configured.',
            ];
        }

        if (! $this->gateway instanceof PaymentGatewayInterface) {
            return [
                'enabled' => true,
                'ready' => false,
                'checked' => false,
                'account_number' => $accountNumber,
                'account_number_masked' => $this->maskAccountNumber($accountNumber),
                'message' => 'No NetBank payment gateway is available for source-account readiness.',
            ];
        }

        try {
            $balance = $this->gateway->checkAccountBalance($accountNumber);
            $availableMinor = (int) ($balance['available_balance'] ?? $balance['balance'] ?? 0);
            $ready = $requiredMinor === null || $availableMinor >= $requiredMinor;

            return [
                'enabled' => true,
                'ready' => $ready,
                'checked' => true,
                'account_number' => $accountNumber,
                'account_number_masked' => $this->maskAccountNumber($accountNumber),
                'balance_minor' => (int) ($balance['balance'] ?? $availableMinor),
                'available_balance_minor' => $availableMinor,
                'required_minor' => $requiredMinor,
                'currency' => (string) ($balance['currency'] ?? config('x-change.pricing.currency', 'PHP')),
                'as_of' => $balance['as_of'] ?? now()->toIso8601String(),
                'message' => $ready
                    ? 'NetBank source account has enough available balance.'
                    : 'NetBank source account cannot cover the requested amount.',
            ];
        } catch (Throwable $e) {
            return [
                'enabled' => true,
                'ready' => false,
                'checked' => true,
                'account_number' => $accountNumber,
                'account_number_masked' => $this->maskAccountNumber($accountNumber),
                'message' => $e->getMessage(),
            ];
        }
    }

    protected function enabled(): bool
    {
        return (bool) config('x-change.provider_runtime.providers.netbank.source_account_readiness.enabled', false);
    }

    protected function sourceAccountNumber(): ?string
    {
        $value = config('x-change.provider_runtime.providers.netbank.source_account_readiness.account_number')
            ?: config('disbursement.source.account_number')
            ?: config('omnipay.gateways.netbank.options.sourceAccountNumber');

        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
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
