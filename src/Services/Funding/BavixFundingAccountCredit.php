<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Funding;

use Bavix\Wallet\Models\Wallet;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\XChange\Contracts\FundingAccountCreditContract;
use LBHurtado\XChange\Exceptions\FundingSettlementDenied;

class BavixFundingAccountCredit implements FundingAccountCreditContract
{
    public function resolve(string $accountReference): object
    {
        if (! str_starts_with($accountReference, 'wallet:')) {
            throw FundingSettlementDenied::because('the Account reference is not a wallet reference');
        }

        $identifier = trim(substr($accountReference, strlen('wallet:')));
        $walletModel = config('wallet.wallet.model', Wallet::class);

        if ($identifier === '' || ! is_a($walletModel, Model::class, true)) {
            throw FundingSettlementDenied::because('the Account wallet reference is invalid');
        }

        $wallet = $walletModel::query()
            ->where('uuid', $identifier)
            ->when(
                ctype_digit($identifier),
                fn ($query) => $query->orWhereKey((int) $identifier),
            )
            ->first();

        if (! $wallet instanceof Model || ! method_exists($wallet, 'deposit')) {
            throw FundingSettlementDenied::because('the Account wallet could not be resolved');
        }

        return $wallet;
    }

    public function credit(object $account, int $amountMinor, array $metadata): object
    {
        if ($amountMinor <= 0 || ! method_exists($account, 'deposit')) {
            throw FundingSettlementDenied::because('the Account credit is invalid');
        }

        return $account->deposit($amountMinor, $metadata, true);
    }
}
