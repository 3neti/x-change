<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Services\Execution\ValueFlowPayoutService;
use RuntimeException;

class ValueFlowPayoutRecoveryCommand extends Command
{
    protected $signature = 'xchange:value-flow:recovery
        {voucher : Pay Code whose delivery is being reviewed}
        {--id= : Exact payout record to inspect or recover}
        {--retry-unaccepted : Retry a confirmed unaccepted submission to its original destination}
        {--confirm-no-transfer : Confirm provider evidence establishes no transfer occurred}
        {--evidence= : Operator evidence reference for the no-transfer confirmation}
        {--json : Output a redacted status report}';

    protected $description = 'Inspect value-flow delivery and recover an evidenced unaccepted submission.';

    public function handle(ValueFlowPayoutService $payouts): int
    {
        $query = DisbursementReconciliation::query()
            ->where('voucher_code', strtoupper(trim((string) $this->argument('voucher'))))
            ->where('claim_type', 'value_flow')
            ->when($this->option('id') !== null, fn ($query) => $query->whereKey((int) $this->option('id')));

        if ($this->option('retry-unaccepted')) {
            if ($this->option('id') === null || ! $this->option('confirm-no-transfer') || trim((string) $this->option('evidence')) === '') {
                $this->error('Recovery requires --id, --confirm-no-transfer and --evidence. Inspect first; unknown outcomes require provider reconciliation.');

                return self::FAILURE;
            }
            $row = (clone $query)->first();
            if ($row === null) {
                $this->error('No matching value-flow payout exists for this Pay Code.');

                return self::FAILURE;
            }
            try {
                $payouts->retryUnacceptedSubmission((int) $row->getKey(), true, (string) $this->option('evidence'));
            } catch (RuntimeException $exception) {
                $this->error($exception->getMessage());

                return self::FAILURE;
            }
        }

        $rows = $query->orderBy('id')->get()->map(fn (DisbursementReconciliation $row): array => [
            'id' => $row->id, 'pay_code' => $row->voucher_code,
            'amount' => $row->amount, 'currency' => $row->currency,
            'destination' => $row->account_number_masked,
            'status' => $row->status, 'accounting_status' => $row->internal_status,
            'needs_review' => (bool) $row->needs_review,
            'next_action' => match (true) {
                $row->internal_status === 'finalized' => 'complete',
                $row->status === 'failed' && $row->internal_status === 'recovery_opened' => 'review_provider_evidence_before_recovery',
                default => 'reconcile_provider_status',
            },
        ])->all();
        if ($this->option('json')) {
            $this->line(json_encode(['payouts' => $rows], JSON_THROW_ON_ERROR));
        } else {
            $this->table(['ID', 'Pay Code', 'Amount', 'Currency', 'Destination', 'Status', 'Accounting', 'Review', 'Next action'], $rows);
        }

        return self::SUCCESS;
    }
}
