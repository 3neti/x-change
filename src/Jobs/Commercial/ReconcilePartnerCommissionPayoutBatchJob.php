<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Jobs\Commercial;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use LBHurtado\XChange\Actions\Commercial\ReconcilePartnerCommissionPayoutBatch;
use LBHurtado\XChange\Enums\PartnerCommissionPayoutBatchStatus;
use LBHurtado\XChange\Models\PartnerCommissionPayoutBatch;

final class ReconcilePartnerCommissionPayoutBatchJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public int $timeout = 60;

    public int $uniqueFor = 120;

    /** @var list<int> */
    public array $backoff = [30, 120, 300, 900];

    public function __construct(public readonly int $batchId)
    {
        $this->onQueue((string) config(
            'x-change.commercial.operations.queue',
            'x-change-funding',
        ));
    }

    /** @return list<object> */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->uniqueId()))
                ->releaseAfter(5)
                ->expireAfter($this->uniqueFor)
                ->shared(),
            new RateLimited('x-change-commercial-settlement'),
        ];
    }

    public function uniqueId(): string
    {
        return 'partner-commission-payout-batch:'.$this->batchId;
    }

    public function handle(ReconcilePartnerCommissionPayoutBatch $action): void
    {
        $batch = PartnerCommissionPayoutBatch::query()->find($this->batchId);

        if (! $batch instanceof PartnerCommissionPayoutBatch
            || $batch->status !== PartnerCommissionPayoutBatchStatus::Pending) {
            return;
        }

        $action->executeAutomatically($batch);
    }
}
