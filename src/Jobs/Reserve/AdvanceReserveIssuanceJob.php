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
use LBHurtado\XChange\Services\Reserve\AdvanceReserveBackedIssuance;
use RuntimeException;

final class AdvanceReserveIssuanceJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $uniqueFor = 120;

    public int $timeout = 60;

    public function __construct(public readonly int $transferId)
    {
        $this->onQueue((string) config('x-change.reserve_transfer.queue', 'x-change-issuance'));
    }

    /** @return list<string> */
    public function tags(): array
    {
        return ['package:x-change', 'lane:issuance', 'job:AdvanceReserveIssuanceJob'];
    }

    public function uniqueId(): string
    {
        return 'reserve-issuance:'.$this->transferId;
    }

    public function handle(AdvanceReserveBackedIssuance $advance): void
    {
        if (! (bool) config('x-change.reserve_transfer.enabled', false)) {
            return;
        }
        $transfer = ReserveTransfer::query()->find($this->transferId);
        $owner = $transfer?->owner;
        if (! $transfer || ! $owner instanceof Model) {
            throw new RuntimeException('The reserve issuance owner is unavailable.');
        }
        if ($transfer->status === 'submitting') {
            if ($transfer->submitted_at !== null && ! $transfer->submitted_at->isAfter(now()->subMinutes(10))) {
                $advance->recoverInterrupted($transfer, $owner);
            }

            return;
        }
        if ($transfer->status === 'held' && ! (bool) config('x-change.reserve_transfer.live_submission_enabled', false)) {
            return;
        }
        if (! in_array($transfer->status, ['held', 'pending', 'provider_settled', 'treasury_posted', 'rejected'], true)) {
            return;
        }
        $providerClass = config('x-change.reserve_transfer.provider_class')
            ?: config('emi.account_transfer_providers.'.$transfer->provider);
        if (! is_string($providerClass) || ! class_exists($providerClass)) {
            throw new RuntimeException('The reserve transfer provider is unavailable.');
        }
        $provider = app($providerClass);
        if (! $provider instanceof AccountTransferProvider) {
            throw new RuntimeException('The reserve transfer provider is invalid.');
        }
        $advance->advance($transfer, $owner, $provider);
    }
}
