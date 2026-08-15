<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Treasury;

use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Enums\TreasuryOperatorCapability;
use LBHurtado\XChange\Enums\TreasuryReconciliationRunStatus;
use LBHurtado\XChange\Models\TreasuryReconciliationRun;
use LBHurtado\XChange\Services\Treasury\TreasuryOperatorAuthority;
use LBHurtado\XChange\Services\Treasury\TreasuryReconciliationRunJournal;

final readonly class ApproveTreasuryReconciliationRun
{
    public function __construct(
        private TreasuryOperatorAuthority $authority,
        private TreasuryReconciliationRunJournal $journal,
    ) {}

    public function handle(TreasuryReconciliationRun $run, Model $checker): TreasuryReconciliationRun
    {
        $this->authority->assertAllows($checker, TreasuryOperatorCapability::ApproveReconciliation);

        return DB::transaction(function () use ($checker, $run): TreasuryReconciliationRun {
            $locked = TreasuryReconciliationRun::query()->lockForUpdate()->findOrFail($run->getKey());

            if (in_array($locked->status, [
                TreasuryReconciliationRunStatus::Approved,
                TreasuryReconciliationRunStatus::Completed,
                TreasuryReconciliationRunStatus::ReviewRequired,
            ], true)) {
                return $locked;
            }

            if ($locked->status !== TreasuryReconciliationRunStatus::AwaitingApproval) {
                throw new DomainException('Only a pending reconciliation request may be approved.');
            }

            if ($locked->maker_type === $checker->getMorphClass()
                && (string) $locked->maker_id === (string) $checker->getKey()) {
                throw new DomainException('The reconciliation checker must be independent from its maker.');
            }

            $locked->forceFill([
                'status' => TreasuryReconciliationRunStatus::Approved,
                'checker_type' => $checker->getMorphClass(),
                'checker_id' => (string) $checker->getKey(),
                'approved_at' => now(),
            ])->save();
            $this->journal->record($locked, 'treasury.reconciliation.approved', $checker);

            return $locked;
        }, attempts: 3);
    }
}
