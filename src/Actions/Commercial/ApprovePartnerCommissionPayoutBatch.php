<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Commercial;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LBHurtado\Wallet\Contracts\SystemUserResolverContract;
use LBHurtado\XChange\Contracts\CommercialOperatorAuthorityContract;
use LBHurtado\XChange\Enums\CommercialOperatorCapability;
use LBHurtado\XChange\Enums\PartnerCommissionPayoutBatchStatus;
use LBHurtado\XChange\Exceptions\CommercialSaleConflict;
use LBHurtado\XChange\Models\PartnerCommissionPayoutBatch;
use LBHurtado\XChange\Services\Commercial\CommercialAccountingJournal;

final readonly class ApprovePartnerCommissionPayoutBatch
{
    public function __construct(
        private CommercialOperatorAuthorityContract $authority,
        private SystemUserResolverContract $systemPrincipal,
        private CommercialAccountingJournal $journal,
    ) {}

    public function execute(
        Model $checker,
        PartnerCommissionPayoutBatch $batch,
        string $approvalReference,
    ): PartnerCommissionPayoutBatch {
        if ($checker->is($this->systemPrincipal->resolve())
            || ! $this->authority->allows($checker, CommercialOperatorCapability::ApproveCommissionPayouts)) {
            throw new AuthorizationException('Operator lacks commission payout approval authority.');
        }

        $approvalReference = trim($approvalReference);

        if ($approvalReference === '') {
            throw new CommercialSaleConflict('Commission payout approval reference is required.');
        }

        return DB::transaction(function () use ($approvalReference, $batch, $checker): PartnerCommissionPayoutBatch {
            $locked = PartnerCommissionPayoutBatch::query()->lockForUpdate()->findOrFail($batch->getKey());

            if ($locked->status === PartnerCommissionPayoutBatchStatus::Approved) {
                if ($locked->checker_type !== $checker->getMorphClass()
                    || (string) $locked->checker_id !== (string) $checker->getKey()
                    || $locked->approval_reference !== $approvalReference) {
                    throw new CommercialSaleConflict('Commission payout approval was replayed with different evidence.');
                }

                return $locked;
            }

            if ($locked->status !== PartnerCommissionPayoutBatchStatus::AwaitingApproval) {
                throw new CommercialSaleConflict('Commission payout is not awaiting approval.');
            }

            if ($locked->maker_type === $checker->getMorphClass()
                && (string) $locked->maker_id === (string) $checker->getKey()) {
                throw new CommercialSaleConflict('Commission payout maker and checker must be different.');
            }

            $locked->forceFill([
                'status' => PartnerCommissionPayoutBatchStatus::Approved,
                'checker_type' => $checker->getMorphClass(),
                'checker_id' => $checker->getKey(),
                'approval_reference' => $approvalReference,
                'approved_at' => now(),
            ])->save();

            $approved = $locked->refresh();
            $this->journal->recordPartnerPayoutBatch(
                $approved,
                $checker->getMorphClass().':'.$checker->getKey(),
                'commercial_payout_checker',
            );

            return $approved;
        }, attempts: 5);
    }
}
