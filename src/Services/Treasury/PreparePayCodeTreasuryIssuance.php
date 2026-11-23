<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Treasury;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\TreasuryPrincipalReferenceResolverContract;
use LBHurtado\XChange\Data\FundingDecisionData;
use LBHurtado\XChange\Exceptions\PayCodeIssuanceFailed;
use LBHurtado\XChange\Services\Banking\AssignedBankTreasuryConnection;
use LBHurtado\XChange\Services\Banking\VoucherBankSourceSnapshot;
use LBHurtado\XChange\Services\Claim\VoucherClaimPolicyResolver;
use LBHurtado\XChange\Services\Execution\ValueFlowAddressResolver;
use LBHurtado\XChange\Services\Execution\VoucherValueFlowPolicy;

final readonly class PreparePayCodeTreasuryIssuance
{
    public function __construct(
        private TreasuryProviderConnectionCatalog $connections,
        private TreasuryPayCodeAccountingService $accounting,
        private VoucherClaimPolicyResolver $claimPolicies,
        private VoucherValueFlowPolicy $valueFlows,
        private ValueFlowAddressResolver $addresses,
        private TreasuryPrincipalReferenceResolverContract $principals,
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

        if (data_get($input, 'voucher_type') === 'payable') {
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

        if (! $voucher instanceof Voucher) {
            throw new PayCodeIssuanceFailed(
                'The Pay Code principal could not be reserved.',
            );
        }

        if ($amountMinor <= 0) {
            return;
        }

        $selectedBankLink = data_get($funding->meta, 'assigned_bank_account_link_id');
        if ($selectedBankLink !== null) {
            $frozen = app(VoucherBankSourceSnapshot::class)->forVoucher($voucher);
            $connection = app(AssignedBankTreasuryConnection::class)->resolve($issuer, (int) $selectedBankLink);
            if (! is_array($frozen)
                || (int) ($frozen['account_link_id'] ?? 0) !== (int) $selectedBankLink
                || ($frozen['treasury_connection_reference'] ?? null) !== $connection->reference
                || ($frozen['treasury_inventory_reference'] ?? null) !== $connection->inventoryReference
                || ($frozen['treasury_resource_reference'] ?? null) !== $connection->settlementResourceReference) {
                throw new PayCodeIssuanceFailed('The selected bank account Treasury connection is not frozen on this Pay Code.');
            }
            $connections = $connections->filter(
                static fn ($candidate): bool => $candidate->reference === $connection->reference,
            )->values();
        } elseif (is_string($sharedReference = data_get($funding->meta, 'treasury_connection_reference'))) {
            $connections = $connections->filter(
                static fn ($candidate): bool => $candidate->reference === $sharedReference,
            )->values();
        }

        if ($connections->count() !== 1) {
            throw new PayCodeIssuanceFailed(
                'Pay Code issuance requires exactly one active Treasury connection.',
            );
        }

        $sourceAddress = $this->valueFlows->source($voucher);

        if ($sourceAddress !== null) {
            $source = $this->addresses->clientFunds(
                $sourceAddress,
                $connections->sole()->reference,
                $currency,
            );

            if ($source->principalReference !== $this->principals->resolve($issuer)) {
                throw new PayCodeIssuanceFailed(
                    'The value flow source must be the funded issuing Account.',
                );
            }
        }

        $destinationAddress = $this->valueFlows->destination($voucher);

        if ($destinationAddress !== null) {
            $this->addresses->instapayDestination($destinationAddress);
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
