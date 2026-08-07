<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Commercial;

use Illuminate\Console\Command;
use LBHurtado\XChange\Enums\PartnerCommissionPayoutBatchStatus;
use LBHurtado\XChange\Jobs\Commercial\ReconcilePartnerCommissionPayoutBatchJob;
use LBHurtado\XChange\Models\PartnerCommissionPayoutBatch;

final class ReconcilePendingPartnerCommissionPayoutsCommand extends Command
{
    protected $signature = 'x-change:commercial:commission:reconcile-pending
        {--limit=50 : Maximum pending payouts to queue}
        {--json}';

    protected $description = 'Queue authoritative status checks for pending partner commission payouts.';

    public function handle(): int
    {
        $ids = PartnerCommissionPayoutBatch::query()
            ->where('status', PartnerCommissionPayoutBatchStatus::Pending->value)
            ->oldest('submitted_at')
            ->limit(max(1, (int) $this->option('limit')))
            ->pluck('id');

        foreach ($ids as $id) {
            ReconcilePartnerCommissionPayoutBatchJob::dispatch((int) $id);
        }

        $payload = [
            'schema' => 'x-change.commercial-commission-reconciliation-dispatch.v1',
            'queued' => $ids->count(),
        ];
        $this->line((bool) $this->option('json')
            ? json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)
            : sprintf('%d pending commission payout(s) queued.', $ids->count()));

        return self::SUCCESS;
    }
}
