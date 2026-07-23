<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Funding;

use Bavix\Wallet\Models\Transaction;
use Bavix\Wallet\Models\Wallet;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\XChange\Contracts\FundingAccountCreditContract;
use LBHurtado\XChange\Contracts\FundingAccountRecoveryContract;
use LBHurtado\XChange\Data\Funding\FundingAccountRecoveryData;
use LBHurtado\XChange\Exceptions\FundingSettlementDenied;

class BavixFundingAccountCredit implements FundingAccountCreditContract, FundingAccountRecoveryContract
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
                fn ($query) => $query->orWhere(
                    $query->getModel()->getQualifiedKeyName(),
                    (int) $identifier,
                ),
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

    public function recover(object $account, int $amountMinor, array $metadata): FundingAccountRecoveryData
    {
        if ($amountMinor <= 0 || ! method_exists($account, 'withdraw')) {
            throw FundingSettlementDenied::because('the Account recovery is invalid');
        }

        $availableAmountMinor = max(0, (int) data_get($account, 'balanceInt', 0));
        $recoveredAmountMinor = min($availableAmountMinor, $amountMinor);
        $transaction = $recoveredAmountMinor > 0
            ? $account->withdraw($recoveredAmountMinor, $metadata)
            : null;

        if ($transaction !== null && ! $transaction instanceof Transaction) {
            throw FundingSettlementDenied::because('the Account ledger did not return a recovery transaction');
        }

        return new FundingAccountRecoveryData(
            requestedAmountMinor: $amountMinor,
            recoveredAmountMinor: $recoveredAmountMinor,
            outstandingAmountMinor: $amountMinor - $recoveredAmountMinor,
            walletTransactionId: $transaction?->getKey(),
            walletTransactionUuid: $transaction?->uuid,
        );
    }
}
