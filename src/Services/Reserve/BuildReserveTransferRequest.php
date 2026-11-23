<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Reserve;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\EmiCore\Data\Providers\AccountTransferRequestData;
use LBHurtado\XChange\Contracts\BankingAccountSelectorContract;
use LBHurtado\XChange\Models\BankAccountAssignment;
use LBHurtado\XChange\Services\Banking\AssignedBankTreasuryConnection;
use LBHurtado\XChange\Services\Banking\SharedBankTreasuryConnection;
use RuntimeException;

/** Builds the transfer only from the selected account and configured platform reserve. */
final readonly class BuildReserveTransferRequest
{
    public function __construct(
        private BankingAccountSelectorContract $selector,
        private AssignedBankTreasuryConnection $assigned,
        private SharedBankTreasuryConnection $shared,
    ) {}

    public function referenceFor(Model $owner, string $idempotencyKey): string
    {
        if (trim($idempotencyKey) === '' || strlen($idempotencyKey) > 200) {
            throw new RuntimeException('A valid issuance key is required.');
        }
        $scope = hash('sha256', json_encode([$owner->getMorphClass(), (string) $owner->getKey(), $idempotencyKey], JSON_THROW_ON_ERROR));

        return 'rsv-'.substr($scope, 0, 32);
    }

    /** @return array{request: AccountTransferRequestData, source_connection: string, reserve_connection: string, account_link_id: int} */
    public function build(Model $owner, ?int $accountLinkId, int $amountMinor, string $currency, string $idempotencyKey): array
    {
        if ($amountMinor <= 0 || $currency !== 'PHP' || trim($idempotencyKey) === '' || strlen($idempotencyKey) > 200) {
            throw new RuntimeException('A valid PHP amount and issuance key are required.');
        }
        $selected = $this->selector->select($owner, 'netbank', 'disbursement', $accountLinkId);
        if ($selected->mode !== 'dedicated' || $selected->accountId === null) {
            throw new RuntimeException('A ready assigned NetBank account is required.');
        }
        $assignment = BankAccountAssignment::query()->whereMorphedTo('owner', $owner)
            ->where('provider', 'netbank')->where('provider_account_link_id', $selected->accountId)->firstOrFail();
        $details = $assignment->details_ciphertext;
        $source = is_array($details) ? ($details['provider_account_number'] ?? $details['account_number'] ?? '') : '';
        $customer = is_array($details) ? ($details['customer_id'] ?? '') : '';
        $sourceName = is_array($details) ? ($details['account_name'] ?? '') : '';
        $destination = config('omnipay.gateways.netbank.options.sourceAccountNumber');
        $destinationName = config('payment-gateway.netbank.funding.corporate_account_name');
        $bankCode = config('x-change.reserve_transfer.destination_bank_code');
        foreach ([$source, $customer, $sourceName, $destination, $destinationName, $bankCode] as $value) {
            if (! is_string($value) || trim($value) === '') {
                throw new RuntimeException('The assigned account or platform reserve details are incomplete.');
            }
        }
        $sourceConnection = $this->assigned->resolve($owner, $selected->accountId);
        $reserveConnection = $this->shared->resolveIfAccountsAssigned('netbank', $currency);
        if ($reserveConnection === null) {
            throw new RuntimeException('The platform reserve account is unavailable.');
        }
        $scope = hash('sha256', json_encode([$owner->getMorphClass(), (string) $owner->getKey(), $idempotencyKey], JSON_THROW_ON_ERROR));
        $request = new AccountTransferRequestData(
            'netbank', $this->referenceFor($owner, $idempotencyKey), 'rsv-'.substr($scope, 0, 32),
            $source, $customer, $destination, $amountMinor, $currency, $sourceName, $destinationName, $bankCode,
        );

        return ['request' => $request, 'source_connection' => $sourceConnection->reference,
            'reserve_connection' => $reserveConnection->reference, 'account_link_id' => $selected->accountId];
    }
}
