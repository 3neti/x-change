<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Treasury;

use Bavix\Wallet\Models\Wallet;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\Wallet\Treasury\Models\TreasuryPosition;
use LBHurtado\XChange\Contracts\TreasuryPositionLedgerResolverContract;
use LBHurtado\XChange\Exceptions\FundingSettlementDenied;

final readonly class BavixTreasuryPositionLedgerResolver implements TreasuryPositionLedgerResolverContract
{
    public function resolve(string $positionReference): object
    {
        $position = TreasuryPosition::query()
            ->where('position_reference', trim($positionReference))
            ->first();
        $walletModel = config('wallet.wallet.model', Wallet::class);

        if (
            $position === null
            || ! is_a($walletModel, Model::class, true)
        ) {
            throw FundingSettlementDenied::because(
                'the Treasury Position ledger could not be resolved',
            );
        }

        $ledger = $walletModel::query()->find($position->internal_ledger_id);

        if (! $ledger instanceof Model) {
            throw FundingSettlementDenied::because(
                'the Treasury Position ledger could not be resolved',
            );
        }

        return $ledger;
    }
}
