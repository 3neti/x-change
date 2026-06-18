<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Illuminate\Support\Str;
use LBHurtado\EmiCore\Enums\ComplianceLevel;
use LBHurtado\EmiCore\Enums\ProviderCode;
use LBHurtado\EmiCore\Enums\VerificationStatus;
use LBHurtado\EmiCore\Enums\WalletStatus;
use LBHurtado\EmiCore\Enums\WalletType;
use LBHurtado\EmiCore\Models\Wallet as EmiWallet;
use LBHurtado\EmiPaynamicsConstellation\Actions\Wallets\GetWalletBalance;
use RuntimeException;

class SyncPaynamicsWalletBalance
{
    /**
     * @return array{wallet: EmiWallet, response: array<string, mixed>, balance_minor: int, balance: float, currency: string}
     */
    public function handle(string $walletId, mixed $owner = null): array
    {
        if ($walletId === '') {
            throw new RuntimeException('Paynamics wallet ID is required for balance sync.');
        }

        $response = GetWalletBalance::run($walletId);
        $currency = (string) data_get($response, 'currency', config('x-change.pricing.currency', 'PHP'));
        $balance = $this->authoritativeBalance($response);
        $wallet = $this->persistProjection($walletId, $balance, $currency, $owner, $response);

        return [
            'wallet' => $wallet,
            'response' => $response,
            'balance_minor' => (int) round($balance * 100),
            'balance' => $balance,
            'currency' => $currency,
        ];
    }

    public function configuredWalletIdForOwner(mixed $owner): ?string
    {
        $defaultIssuerWalletId = $this->stringValue(config('constellation.default_issuer_wallet_id'));

        if ($defaultIssuerWalletId !== null) {
            return $defaultIssuerWalletId;
        }

        $settlementWalletId = $this->stringValue(config('constellation.settlement_wallet_id'));

        if ($settlementWalletId === null) {
            return null;
        }

        $ownerEmail = $this->stringValue(data_get($owner, 'email'));
        $ownerMobile = $this->stringValue(data_get($owner, 'mobile'));

        if ($ownerEmail !== null && $ownerEmail === $this->stringValue(config('constellation.settlement.email'))) {
            return $settlementWalletId;
        }

        if ($ownerMobile !== null && $ownerMobile === $this->stringValue(config('constellation.company.mobile_no'))) {
            return $settlementWalletId;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    protected function authoritativeBalance(array $response): float
    {
        return (float) (
            data_get($response, 'withdrawable_balance')
            ?? data_get($response, 'wallet_balance')
            ?? data_get($response, 'current_balance')
            ?? 0
        );
    }

    /**
     * @param  array<string, mixed>  $response
     */
    protected function persistProjection(string $walletId, float $balance, string $currency, mixed $owner, array $response): EmiWallet
    {
        $attributes = [
            'provider_code' => ProviderCode::PaynamicsConstellation->value,
            'provider_wallet_id' => $walletId,
            'wallet_type' => WalletType::Customer->value,
            'status' => WalletStatus::Active->value,
            'compliance_level' => ComplianceLevel::Level1->value,
            'verification_status' => VerificationStatus::Approved->value,
            'balance_cached' => number_format($balance, 2, '.', ''),
            'currency' => $currency,
            'meta' => array_filter([
                'wallet_balance' => data_get($response, 'wallet_balance'),
                'current_balance' => data_get($response, 'current_balance'),
                'withdrawable_balance' => data_get($response, 'withdrawable_balance'),
                'remaining_wallet_limit' => data_get($response, 'remaining_wallet_limit'),
                'remaining_outflow_limit' => data_get($response, 'remaining_outflow_limit'),
                'remaining_inflow_limit' => data_get($response, 'remaining_inflow_limit'),
            ], static fn (mixed $value): bool => $value !== null),
        ];

        $existing = EmiWallet::query()->where('provider_wallet_id', $walletId)->first();

        if ($existing !== null) {
            $existing->forceFill($attributes)->save();

            return $existing->fresh() ?? $existing;
        }

        if (! is_object($owner) || ! method_exists($owner, 'getKey')) {
            throw new RuntimeException('An Eloquent owner is required when creating a Paynamics wallet projection.');
        }

        return EmiWallet::unguarded(function () use ($owner, $walletId, $attributes): EmiWallet {
            return EmiWallet::query()->create([
                'holder_type' => $owner::class,
                'holder_id' => $owner->getKey(),
                'name' => 'Paynamics Wallet Balance',
                'slug' => Str::limit(Str::slug('paynamics-'.$walletId, '-'), 255, ''),
                'uuid' => (string) Str::uuid(),
                'description' => 'Provider wallet balance projection',
                'balance' => 0,
                'decimal_places' => 2,
                ...$attributes,
            ]);
        });
    }

    protected function stringValue(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
