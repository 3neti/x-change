<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Treasury;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Data\FundingDecisionData;
use LBHurtado\XChange\Exceptions\PayCodeIssuanceFailed;
use LBHurtado\XChange\Services\Claim\VoucherClaimPolicyResolver;

final readonly class PreparePayCodeTreasuryIssuance
{
    public function __construct(
        private TreasuryProviderConnectionCatalog $connections,
        private TreasuryPayCodeAccountingService $accounting,
        private VoucherClaimPolicyResolver $claimPolicies,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $issued
     */
    public function handle(
        Model $issuer,
        array $input,
        array $issued,
        FundingDecisionData $funding,
    ): void {
        if (! $this->usesTreasuryPositions($funding)) {
            return;
        }

        $policy = $this->claimPolicies->resolveInstructions(
            VoucherInstructionsData::from($input),
        );

        if (! $policy->permits('provider_disbursement')) {
            return;
        }

        $voucher = Voucher::query()->find((int) ($issued['voucher_id'] ?? 0));
        $currency = mb_strtoupper(trim((string) ($issued['currency'] ?? '')));
        $amountMinor = (int) round(((float) ($issued['amount'] ?? 0)) * 100);
        $provider = mb_strtolower(trim((string) data_get(
            $funding->meta,
            'provider',
        )));
        $connections = collect($this->connections->active())
            ->filter(
                static fn ($connection): bool => $connection->provider === $provider
                    && $connection->currency === $currency,
            )
            ->values();

        if (! $voucher instanceof Voucher || $amountMinor <= 0) {
            throw new PayCodeIssuanceFailed(
                'The Pay Code principal could not be reserved.',
            );
        }

        if ($connections->count() !== 1) {
            throw new PayCodeIssuanceFailed(
                'Pay Code issuance requires exactly one active Treasury connection.',
            );
        }

        $this->accounting->reserve(
            accountOwner: $issuer,
            voucher: $voucher,
            connectionReference: $connections->sole()->reference,
            providerPrincipalMinor: $amountMinor,
            currency: $currency,
        );
    }

    private function usesTreasuryPositions(FundingDecisionData $funding): bool
    {
        return $funding->authority === 'local_ledger'
            && data_get($funding->meta, 'topology') === 'ledger_pooled'
            && (bool) config('x-change.commercial.enabled', true);
    }
}
