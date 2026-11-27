<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Jobs\Reserve;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use LBHurtado\EmiCore\Contracts\AccountTransferProvider;
use LBHurtado\XChange\Models\ReserveTransfer;
use LBHurtado\XChange\Services\Reserve\AdvanceReserveReturn;
use LBHurtado\XChange\Services\Reserve\DurableReserveTransfer;
use RuntimeException;

final class AdvanceReserveReturnJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $uniqueFor = 120;

    public int $timeout = 60;

    public function __construct(public readonly int $transferId)
    {
        $this->onQueue((string) config('x-change.reserve_transfer.queue', 'x-change-issuance'));
    }

    public function uniqueId(): string
    {
        return 'reserve-return:'.$this->transferId;
    }

    public function handle(AdvanceReserveReturn $advance, DurableReserveTransfer $transfers): void
    {
        if (! (bool) config('x-change.reserve_transfer.returns_enabled', false)) {
            return;
        }
        $transfer = ReserveTransfer::query()->find($this->transferId);
        $owner = $transfer?->owner;
        if (! $transfer || ! $owner instanceof Model || $transfer->transfer_kind !== 'return') {
            throw new RuntimeException('The reserve return is unavailable.');
        }
        if ($transfer->status === 'submitting') {
            if ($transfer->submitted_at !== null && ! $transfer->submitted_at->isAfter(now()->subMinutes(10))) {
                $transfers->markInterruptedUncertain($transfer);
            }

            return;
        }
        if ($transfer->status === 'held' && ! (bool) config('x-change.reserve_transfer.live_submission_enabled', false)) {
            return;
        }
        if (! in_array($transfer->status, ['held', 'pending', 'provider_settled', 'rejected'], true)) {
            return;
        }
        $providerClass = config('x-change.reserve_transfer.provider_class')
            ?: config('emi.account_transfer_providers.'.$transfer->provider);
        if (! is_string($providerClass) || ! class_exists($providerClass)) {
            throw new RuntimeException('The reserve return provider is unavailable.');
        }
        $provider = app($providerClass);
        if (! $provider instanceof AccountTransferProvider) {
            throw new RuntimeException('The reserve return provider is invalid.');
        }
        $advance->advance($transfer, $owner, $provider);
    }
}
