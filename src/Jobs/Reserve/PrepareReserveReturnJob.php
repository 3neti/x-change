<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Jobs\Reserve;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Services\Reserve\PrepareReserveReturn;

final class PrepareReserveReturnJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $uniqueFor = 120;

    public int $timeout = 60;

    public function __construct(
        public readonly int $voucherId,
        public readonly string $terminalReason,
    ) {
        $this->onQueue((string) config('x-change.reserve_transfer.queue', 'x-change-issuance'));
    }

    public function uniqueId(): string
    {
        return 'reserve-return-prepare:'.$this->voucherId.':'.$this->terminalReason;
    }

    /** @return list<string> */
    public function tags(): array
    {
        return ['package:x-change', 'lane:issuance', 'job:PrepareReserveReturnJob'];
    }

    public function handle(PrepareReserveReturn $returns): void
    {
        if (! (bool) config('x-change.reserve_transfer.returns_enabled', false)) {
            return;
        }
        $voucher = Voucher::query()->with('owner')->find($this->voucherId);
        if (! $voucher instanceof Voucher) {
            return;
        }
        $return = $returns->prepare($voucher, $this->terminalReason);
        if ($return !== null) {
            AdvanceReserveReturnJob::dispatch((int) $return->getKey());
        }
    }
}
