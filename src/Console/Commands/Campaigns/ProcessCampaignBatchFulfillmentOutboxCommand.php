<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Campaigns;

use Illuminate\Console\Command;
use LBHurtado\XChange\Models\CampaignBatchFulfillmentOutbox;
use LBHurtado\XChange\Services\Campaigns\CampaignBatchFulfillmentOutboxProcessor;
use Throwable;

final class ProcessCampaignBatchFulfillmentOutboxCommand extends Command
{
    protected $signature = 'x-change:campaigns:process-batches {--limit=10}';

    protected $description = 'Process independently approved Campaign lifecycle batches.';

    public function handle(CampaignBatchFulfillmentOutboxProcessor $processor): int
    {
        $limit = max(1, min(100, (int) $this->option('limit')));
        $failed = 0;
        $events = CampaignBatchFulfillmentOutbox::query()
            ->where('status', 'pending')
            ->where('available_at', '<=', now())
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($events as $event) {
            try {
                $processor->process($event);
            } catch (Throwable $exception) {
                report($exception);
                $failed++;
            }
        }

        $this->line(sprintf('Processed %d Campaign batch event(s); %d failed.', $events->count(), $failed));

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
