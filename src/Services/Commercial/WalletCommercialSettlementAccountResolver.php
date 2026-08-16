<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\XChange\Contracts\CommercialSettlementAccountResolverContract;
use LBHurtado\XChange\Contracts\FundingAccountCreditContract;
use LBHurtado\XChange\Contracts\TreasuryAccountPortfolioProvisioningContract;
use LBHurtado\XChange\Contracts\TreasuryPrincipalReferenceResolverContract;
use LBHurtado\XChange\Data\Treasury\TreasuryProviderConnectionData;
use LBHurtado\XChange\Exceptions\CommercialSaleConflict;

final readonly class WalletCommercialSettlementAccountResolver implements CommercialSettlementAccountResolverContract
{
    public function __construct(
        private FundingAccountCreditContract $accounts,
        private TreasuryPrincipalReferenceResolverContract $principalReferences,
        private TreasuryAccountPortfolioProvisioningContract $portfolios,
    ) {}

    public function resolveClientFundsPosition(
        string $accountReference,
        string $principalReference,
        TreasuryProviderConnectionData $connection,
    ): string {
        $account = $this->accounts->resolve($accountReference);
        $owner = data_get($account, 'holder');

        if (! $owner instanceof Model
            || ! hash_equals($principalReference, $this->principalReferences->resolve($owner))) {
            throw new CommercialSaleConflict(
                'The governed commercial settlement Account does not belong to its frozen principal.',
            );
        }

        $positions = $this->portfolios->provision($owner, [$connection->reference])->positions;
        $matches = array_values(array_filter(
            $positions,
            static fn (TreasuryPositionData $position): bool => $position->purpose === TreasuryPositionPurpose::ClientFunds
                && $position->provider === $connection->provider
                && $position->connectionReference === $connection->reference
                && $position->currency === $connection->currency,
        ));

        if (count($matches) !== 1) {
            throw new CommercialSaleConflict(
                'The governed commercial settlement Account has no unique compatible Client Funds Position.',
            );
        }

        return $matches[0]->positionReference;
    }
}
