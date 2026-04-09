<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\XChange\Contracts\DisbursementReconciliationContract;
use LBHurtado\XChange\Contracts\DisbursementReconciliationStoreContract;

class ReconcilePendingDisbursementsCommand extends Command
{
    protected $signature = 'xchange:reconcile-pending-disbursements {--limit=50}';

    protected $description = 'Reconcile pending or unknown disbursement records.';

    public function handle(): int
    {
        /** @var DisbursementReconciliationStoreContract $store */
        $store = app(DisbursementReconciliationStoreContract::class);

        /** @var DisbursementReconciliationContract $service */
        $service = app(DisbursementReconciliationContract::class);

        $limit = (int) $this->option('limit');

        $records = $store->getPending($limit);

        if ($records === []) {
            $this->info('No pending disbursements to reconcile.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Reconciling %d disbursement(s)...', count($records)));

        foreach ($records as $record) {
            $updated = $service->reconcile($record);

            $this->line(sprintf(
                '[%d] %s → %s (%s)',
                $updated->id ?? 0,
                $updated->voucher_code,
                $updated->status,
                $updated->internal_status ?? 'n/a',
            ));
        }

        $this->info('Reconciliation run complete.');

        return self::SUCCESS;
    }
}
