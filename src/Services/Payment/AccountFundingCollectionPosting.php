<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Payment;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Contracts\SystemUserResolverContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionOperationContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionReleaseData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\XChange\Contracts\FundingAccountCreditContract;
use LBHurtado\XChange\Contracts\TreasuryAccountPortfolioProvisioningContract;
use LBHurtado\XChange\Contracts\VoucherCollectionPostingContract;
use LBHurtado\XChange\Data\Payment\ConfirmedVoucherCollectionData;
use LBHurtado\XChange\Data\Payment\VoucherCollectionPostingData;
use LBHurtado\XChange\Enums\FundingRequestStatus;
use LBHurtado\XChange\Models\FundingRequest;
use RuntimeException;

final readonly class AccountFundingCollectionPosting implements VoucherCollectionPostingContract
{
    public function __construct(
        private SystemUserResolverContract $systemUsers,
        private FundingAccountCreditContract $accounts,
        private TreasuryAccountPortfolioProvisioningContract $portfolios,
        private TreasuryPositionOperationContract $operations,
    ) {}

    public function driver(): string
    {
        return 'x_change_account_funding';
    }

    public function post(
        Voucher $voucher,
        ConfirmedVoucherCollectionData $collection,
    ): VoucherCollectionPostingData {
        $request = FundingRequest::query()
            ->where('voucher_id', $voucher->getKey())
            ->lockForUpdate()
            ->sole();

        if ($request->status !== FundingRequestStatus::PayCodeIssued) {
            throw new RuntimeException(
                'Reviewed Account Funding is not approved for Treasury payment.',
            );
        }

        $system = $this->systemUsers->resolve();
        $recipient = data_get(
            $this->accounts->resolve($request->account_reference),
            'holder',
        );

        if (! $system instanceof Model || ! $recipient instanceof Model) {
            throw new RuntimeException(
                'Reviewed Account Funding principals could not be resolved.',
            );
        }

        if (
            ! $recipient instanceof Authenticatable
            || $recipient::class !== $request->requester_type
            || (string) $recipient->getKey() !== $request->requester_id
        ) {
            throw new RuntimeException(
                'Reviewed Account Funding recipient binding is invalid.',
            );
        }

        $connectionReference = trim((string) $request->connection_reference);
        $source = $this->position(
            $this->portfolios->provision(
                $system,
                [$connectionReference],
            )->positions,
            TreasuryPositionPurpose::PayCodeReserve,
        );
        $destination = $this->position(
            $this->portfolios->provision(
                $recipient,
                [$connectionReference],
            )->positions,
            TreasuryPositionPurpose::ClientFunds,
        );
        $scope = hash('sha256', implode('|', [
            'reviewed-account-funding',
            (string) $request->reference,
            (string) $voucher->getKey(),
            (string) $collection->amountMinor,
            mb_strtoupper($collection->currency),
        ]));
        $release = $this->operations->release(
            new TreasuryPositionReleaseData(
                operationReference: 'reviewed-account-funding-payment:'.$scope,
                sourcePositionReference: $source->positionReference,
                destinationPositionReference: $destination->positionReference,
                amountMinor: $collection->amountMinor,
                currency: mb_strtoupper($collection->currency),
                idempotencyKey: 'reviewed-account-funding-payment-key:'.$scope,
                externalReference: $collection->authorityReference,
                metadata: [
                    'source' => 'x_change_reviewed_account_funding_collection',
                    'funding_request_reference' => $request->reference,
                    'pay_code_id' => (int) $voucher->getKey(),
                    'provider_calls' => false,
                    'provider_inventory_changed' => false,
                ],
            ),
        );

        return new VoucherCollectionPostingData(
            treasuryOperationReference: $release->operationReference,
            metadata: [
                'funding_request_reference' => $request->reference,
                'provider_calls' => false,
                'provider_inventory_changed' => false,
            ],
        );
    }

    /**
     * @param  list<TreasuryPositionData>  $positions
     */
    private function position(
        array $positions,
        TreasuryPositionPurpose $purpose,
    ): TreasuryPositionData {
        $matches = array_values(array_filter(
            $positions,
            static fn (TreasuryPositionData $position): bool => $position->purpose === $purpose
                && $position->status === 'active',
        ));

        if (count($matches) !== 1) {
            throw new RuntimeException(
                "Reviewed Account Funding requires one active {$purpose->value} Treasury Position.",
            );
        }

        return $matches[0];
    }
}
