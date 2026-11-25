<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Reserve;

use Illuminate\Console\Command;
use LBHurtado\XChange\Jobs\Reserve\AdvanceReserveIssuanceJob;
use LBHurtado\XChange\Models\ReserveTransfer;

final class DispatchReserveIssuancesCommand extends Command
{
    protected $signature = 'xchange:reserve:dispatch-issuances {--limit=100}';

    protected $description = 'Dispatch safe advancement of pending reserve-backed issuances';

    public function handle(): int
    {
        if (! (bool) config('x-change.reserve_transfer.enabled', false)) {
            $this->components->info('Reserve-backed issuance is disabled.');

            return self::SUCCESS;
        }
        $count = 0;
        ReserveTransfer::query()->where(function ($query): void {
            $query->whereIn('status', ['held', 'pending', 'provider_settled', 'treasury_posted', 'rejected'])
                ->orWhere(function ($query): void {
                    $query->where('status', 'submitting')->where('submitted_at', '<=', now()->subMinutes(10));
                });
        })->oldest('updated_at')->limit(max(1, min(1000, (int) $this->option('limit'))))->get()
            ->each(function (ReserveTransfer $transfer) use (&$count): void {
                AdvanceReserveIssuanceJob::dispatch($transfer->getKey());
                $count++;
            });
        $this->components->info("Dispatched {$count} reserve issuance(s).");

        return self::SUCCESS;
    }
}
