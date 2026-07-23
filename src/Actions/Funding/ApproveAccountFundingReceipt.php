<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Contracts\AuditLoggerContract;
use LBHurtado\XChange\Enums\AccountFundingReceiptStatus;
use LBHurtado\XChange\Exceptions\FundingSettlementDenied;
use LBHurtado\XChange\Models\AccountFundingReceipt;
use LBHurtado\XChange\Models\StandingFundingAddress;

final class ApproveAccountFundingReceipt
{
    public function __construct(
        private readonly SettleAccountFundingReceipt $settle,
        private readonly AuditLoggerContract $audit,
    ) {}

    public function handle(AccountFundingReceipt $receipt, Model $actor): AccountFundingReceipt
    {
        $approved = DB::transaction(function () use ($receipt, $actor): AccountFundingReceipt {
            $address = StandingFundingAddress::query()
                ->lockForUpdate()
                ->findOrFail($receipt->standing_funding_address_id);
            $locked = AccountFundingReceipt::query()
                ->lockForUpdate()
                ->findOrFail($receipt->getKey());

            if ($address->owner_type !== $actor::class
                || (string) $address->owner_id !== (string) $actor->getKey()) {
                throw new AuthorizationException('This Account Funding Receipt belongs to another operator.');
            }

            if ($locked->status === AccountFundingReceiptStatus::Settled) {
                return $locked;
            }

            if ($locked->status !== AccountFundingReceiptStatus::AwaitingApproval) {
                throw FundingSettlementDenied::because('the Account Funding Receipt is not awaiting approval');
            }

            $locked->status = AccountFundingReceiptStatus::Ready;
            $locked->saveQuietly();

            return $locked->refresh();
        }, attempts: 3);

        $this->audit->log('funding.standing_address.receipt_approved', [
            'account_funding_receipt_reference' => $approved->reference,
            'actor_type' => $actor::class,
            'actor_id' => (string) $actor->getKey(),
            'provider' => $approved->provider_code,
        ]);

        return $this->settle->handle($approved);
    }
}
