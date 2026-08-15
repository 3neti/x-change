<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Treasury;

use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Data\Treasury\TreasuryOpeningBalanceConnectionData;
use LBHurtado\XChange\Enums\TreasuryOpeningBalanceStatus;
use LBHurtado\XChange\Enums\TreasuryOperatorCapability;
use LBHurtado\XChange\Enums\TreasuryReconciliationRunStatus;
use LBHurtado\XChange\Models\TreasuryReconciliationRun;
use LBHurtado\XChange\Services\Treasury\TreasuryOpeningBalanceReconciliationService;
use LBHurtado\XChange\Services\Treasury\TreasuryOperatorAuthority;
use LBHurtado\XChange\Services\Treasury\TreasuryReconciliationRunJournal;
use Throwable;

final readonly class ExecuteTreasuryReconciliationRun
{
    public function __construct(
        private TreasuryOperatorAuthority $authority,
        private TreasuryOpeningBalanceReconciliationService $reconciliation,
        private TreasuryReconciliationRunJournal $journal,
    ) {}

    public function handle(TreasuryReconciliationRun $run, Model $operator): TreasuryReconciliationRun
    {
        $this->authority->assertAllows($operator, TreasuryOperatorCapability::ExecuteReconciliation);

        return Cache::lock(
            'x-change:treasury:reconciliation-run:'.hash('sha256', $run->reference),
            max(1, (int) config('x-change.treasury.reconciliation_lock_seconds', 60)),
        )->block(
            max(0, (int) config('x-change.treasury.reconciliation_lock_wait_seconds', 5)),
            fn (): TreasuryReconciliationRun => $this->executeLocked($run, $operator),
        );
    }

    private function executeLocked(TreasuryReconciliationRun $run, Model $operator): TreasuryReconciliationRun
    {
        $prepared = DB::transaction(function () use ($run): TreasuryReconciliationRun {
            $locked = TreasuryReconciliationRun::query()->lockForUpdate()->findOrFail($run->getKey());

            if (in_array($locked->status, [
                TreasuryReconciliationRunStatus::Completed,
                TreasuryReconciliationRunStatus::ReviewRequired,
            ], true)) {
                return $locked;
            }

            if ($locked->status !== TreasuryReconciliationRunStatus::Approved) {
                throw new DomainException('Only an approved reconciliation request may contact the provider.');
            }

            $locked->forceFill([
                'attempt_count' => $locked->attempt_count + 1,
                'last_attempt_at' => now(),
                'failed_at' => null,
                'reason' => null,
            ])->save();

            return $locked;
        }, attempts: 3);

        if ($prepared->status !== TreasuryReconciliationRunStatus::Approved) {
            return $prepared;
        }

        $this->journal->record($prepared, 'treasury.reconciliation.execution_authorized', $operator);

        try {
            $result = $this->reconciliation->reconcile([$prepared->connection_reference])->connections[0]
                ?? throw new DomainException('The provider returned no reconciliation result.');
        } catch (Throwable $exception) {
            report($exception);

            return $this->recordFailure($prepared);
        }

        return $this->recordResult($prepared, $result, $operator);
    }

    private function recordResult(
        TreasuryReconciliationRun $run,
        TreasuryOpeningBalanceConnectionData $result,
        Model $operator,
    ): TreasuryReconciliationRun {
        return DB::transaction(function () use ($operator, $result, $run): TreasuryReconciliationRun {
            $locked = TreasuryReconciliationRun::query()->lockForUpdate()->findOrFail($run->getKey());
            $status = in_array($result->status, [
                TreasuryOpeningBalanceStatus::Reconciled,
                TreasuryOpeningBalanceStatus::Recognized,
            ], true)
                ? TreasuryReconciliationRunStatus::Completed
                : TreasuryReconciliationRunStatus::ReviewRequired;
            $locked->forceFill([
                'status' => $status,
                'provider_balance_minor' => $result->providerBalanceMinor,
                'inventory_balance_minor' => $result->inventoryBalanceMinor,
                'position_balance_minor' => $result->positionBalanceMinor,
                'difference_minor' => $result->differenceMinor,
                'evidence_reference' => $result->evidenceReference,
                'observed_at' => CarbonImmutable::parse($result->observedAt),
                'inventory_operation_reference' => $result->inventoryOperationReference,
                'position_operation_reference' => $result->positionOperationReference,
                'reason' => $result->reason,
                'completed_at' => now(),
                'failed_at' => null,
            ])->save();
            $this->journal->record(
                $locked,
                $status === TreasuryReconciliationRunStatus::Completed
                    ? 'treasury.reconciliation.executed'
                    : 'treasury.reconciliation.review_required',
                $operator,
            );

            return $locked;
        }, attempts: 3);
    }

    private function recordFailure(TreasuryReconciliationRun $run): TreasuryReconciliationRun
    {
        return DB::transaction(function () use ($run): TreasuryReconciliationRun {
            $locked = TreasuryReconciliationRun::query()->lockForUpdate()->findOrFail($run->getKey());
            $locked->forceFill([
                'status' => TreasuryReconciliationRunStatus::Failed,
                'reason' => 'provider-balance-check-failed',
                'failed_at' => now(),
            ])->save();
            $this->journal->record($locked, 'treasury.reconciliation.failed', 'system');

            return $locked;
        }, attempts: 3);
    }
}
