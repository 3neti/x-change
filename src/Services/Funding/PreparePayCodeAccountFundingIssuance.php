<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Funding;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Enums\PayCodeSettlementDestination;
use LBHurtado\XChange\Exceptions\PayCodeIssuanceFailed;
use LBHurtado\XChange\Services\Treasury\TreasuryPayCodeAccountingService;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;

final readonly class PreparePayCodeAccountFundingIssuance
{
    public function __construct(
        private TreasuryProviderConnectionCatalog $connections,
        private TreasuryPayCodeAccountingService $accounting,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $issued
     * @param  array<string, mixed>  $commercial
     */
    public function handle(
        Model $issuer,
        array $input,
        array $issued,
        array $commercial,
        string $provider,
    ): void {
        if (! $this->isRequested($input)) {
            return;
        }

        if (! (bool) config('x-change.commercial.enabled', true)) {
            throw new PayCodeIssuanceFailed(
                'Account Funding Pay Codes require the commercial posting engine.',
            );
        }

        $providerCostMinor = collect((array) ($commercial['allocations'] ?? []))
            ->where('category', 'provider_cost')
            ->sum(static fn (array $allocation): int => (int) ($allocation['amount_minor'] ?? 0));

        if ($providerCostMinor !== 0) {
            throw new PayCodeIssuanceFailed(
                'Account Funding Pay Codes cannot include a provider payout cost.',
            );
        }

        $voucher = Voucher::query()->find((int) ($issued['voucher_id'] ?? 0));
        $currency = mb_strtoupper(trim((string) ($issued['currency'] ?? '')));
        $amountMinor = (int) round(((float) ($issued['amount'] ?? 0)) * 100);
        $connection = collect($this->connections->active())
            ->filter(
                static fn ($connection): bool => $connection->provider === mb_strtolower(trim($provider))
                    && $connection->currency === $currency,
            )
            ->sole();

        if (! $voucher instanceof Voucher || $amountMinor <= 0) {
            throw new PayCodeIssuanceFailed(
                'Account Funding Pay Code principal could not be reserved.',
            );
        }

        $this->accounting->reserve(
            accountOwner: $issuer,
            voucher: $voucher,
            connectionReference: $connection->reference,
            providerPrincipalMinor: $amountMinor,
            currency: $currency,
        );

        $metadata = is_array($voucher->metadata) ? $voucher->metadata : [];
        data_set($metadata, 'treasury.account_funding', [
            'status' => 'ready',
            'destinations' => [
                PayCodeSettlementDestination::AccountFunding->value,
            ],
            'pricing_profile' => 'account-funding-v1',
            'provider_cost_minor' => 0,
            'provider_calls' => false,
            'commercial_sale_reference' => $commercial['commercial_sale_reference'] ?? null,
        ]);
        $voucher->forceFill(['metadata' => $metadata])->saveQuietly();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function isRequested(array $input): bool
    {
        $destinations = collect((array) data_get(
            $input,
            'metadata.custom.settlement.destinations',
            [],
        ))
            ->map(static fn (mixed $destination): string => mb_strtolower(trim((string) $destination)))
            ->all();

        return in_array(
            PayCodeSettlementDestination::AccountFunding->value,
            $destinations,
            true,
        ) && data_get(
            $input,
            'metadata.custom.settlement.account_funding.pricing_profile',
        ) === 'account-funding-v1';
    }
}
