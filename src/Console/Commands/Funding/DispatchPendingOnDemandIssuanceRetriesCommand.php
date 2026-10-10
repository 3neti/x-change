<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Funding;

use Illuminate\Console\Command;
use LBHurtado\XChange\Enums\PayCodeIssuanceFundingOrderStatus;
use LBHurtado\XChange\Jobs\Funding\ResumeOnDemandPayCodeIssuanceJob;
use LBHurtado\XChange\Models\PayCodeIssuanceFundingOrder;

final class DispatchPendingOnDemandIssuanceRetriesCommand extends Command
{
    protected $signature = 'xchange:funding:dispatch-issuance-retries {--limit=100}';

    protected $description = 'Dispatch admitted paid-order issuance retries that have not started';

    public function handle(): int
    {
        $dispatched = 0;

        PayCodeIssuanceFundingOrder::query()
            ->where('status', PayCodeIssuanceFundingOrderStatus::IssuanceAttention)
            ->whereNotNull('funded_at')
            ->where('metadata->issuance_retry->pending', true)
            ->oldest('id')
            ->limit(max(1, (int) $this->option('limit')))
            ->get()
            ->each(function (PayCodeIssuanceFundingOrder $order) use (&$dispatched): void {
                ResumeOnDemandPayCodeIssuanceJob::dispatch($order->getKey());
                $dispatched++;
            });

        $this->components->info("Inspected {$dispatched} pending issuance retry request(s).");

        return self::SUCCESS;
    }
}
