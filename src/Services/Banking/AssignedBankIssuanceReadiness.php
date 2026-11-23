<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Banking;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\XChange\Contracts\BankingAccountSelectorContract;
use LBHurtado\XChange\Data\FundingDecisionData;
use LBHurtado\XChange\Exceptions\InsufficientWalletBalance;
use LBHurtado\XChange\Models\BankAccountAssignment;
use LBHurtado\XChange\Services\CheckProviderSourceAccountReadiness;
use LBHurtado\XChange\Services\Treasury\TreasuryAccountBalanceReadModel;

final readonly class AssignedBankIssuanceReadiness
{
    public function __construct(
        private BankingAccountSelectorContract $accounts,
        private AssignedBankTreasuryConnection $connections,
        private TreasuryAccountBalanceReadModel $balances,
        private CheckProviderSourceAccountReadiness $providerReadiness,
    ) {}

    public function assess(Model $owner, int $accountLinkId, int $requiredMinor): FundingDecisionData
    {
        if ($requiredMinor <= 0) {
            throw new InsufficientWalletBalance('The requested issuance amount is invalid.');
        }
        $selected = $this->accounts->select($owner, 'netbank', 'disbursement', $accountLinkId);
        $assignment = BankAccountAssignment::query()->whereMorphedTo('owner', $owner)
            ->where('provider_account_link_id', $selected->accountId)->firstOrFail();
        $connection = $this->connections->resolve($owner, $accountLinkId);
        $available = $this->balances->connectionBalanceMinor($owner, 'netbank', $connection->reference, 'PHP');
        if ($available === null || $available < $requiredMinor) {
            throw new InsufficientWalletBalance('The selected account does not have enough available client funds.');
        }
        $details = $assignment->details_ciphertext;
        $accountNumber = is_array($details) ? ($details['provider_account_number'] ?? $details['account_number'] ?? null) : null;
        if (! is_string($accountNumber) || $accountNumber === ''
            || preg_replace('/\D+/', '', $accountNumber) !== ($details['account_number'] ?? null)) {
            throw new InsufficientWalletBalance('The selected account source is unavailable.');
        }
        $provider = $this->providerReadiness->handle($requiredMinor, 'netbank', $accountNumber);
        if (($provider['ready'] ?? false) !== true || ($provider['checked'] ?? false) !== true
            || ($provider['currency'] ?? null) !== 'PHP'
            || ! is_int($provider['available_balance_minor'] ?? null)
            || $provider['available_balance_minor'] < $requiredMinor) {
            throw new InsufficientWalletBalance('The selected bank account balance could not cover this issuance.');
        }

        return FundingDecisionData::allowed(
            authority: 'local_ledger', availableMinor: $available, requiredMinor: $requiredMinor, currency: 'PHP',
            freshAsOf: now()->toIso8601String(),
            meta: [
                'provider' => 'netbank',
                'topology' => 'ledger_pooled',
                'assigned_bank_account_link_id' => $accountLinkId,
                'treasury_connection_reference' => $connection->reference,
            ],
        );
    }
}
