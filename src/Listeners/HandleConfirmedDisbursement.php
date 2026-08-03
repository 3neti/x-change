<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Listeners;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Events\DisbursementConfirmed;
use LBHurtado\XChange\Jobs\Redemption\DispatchVoucherRedemptionFeedbackJob;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Models\VoucherClaim;
use LBHurtado\XChange\Services\Treasury\TreasuryPayCodeAccountingService;

final readonly class HandleConfirmedDisbursement
{
    public function __construct(
        private TreasuryPayCodeAccountingService $accounting,
    ) {}

    public function handle(DisbursementConfirmed $event): void
    {
        $reconciliation = $event->reconciliation->fresh();
        $voucher = Voucher::query()->find($reconciliation->voucher_id);

        if (! $voucher instanceof Voucher) {
            return;
        }

        $reservation = data_get($voucher->metadata, 'treasury.pay_code_reservation', []);

        if (data_get($reservation, 'status') === 'settled') {
            return;
        }

        if (data_get($reservation, 'status') !== 'reserved') {
            $this->queueTerminalFeedback($voucher, $reconciliation);

            return;
        }

        $owner = $voucher->owner;

        if (! $owner instanceof Model) {
            Log::error('[XChange] Confirmed disbursement has no Pay Code owner.', [
                'voucher_code' => $voucher->code,
                'reconciliation_id' => $reconciliation->getKey(),
            ]);

            return;
        }

        $this->accounting->settle(
            accountOwner: $owner,
            voucher: $voucher,
            reconciliation: $reconciliation,
            connectionReference: (string) data_get($reservation, 'connection_reference'),
            reservedPrincipalMinor: (int) data_get($reservation, 'amount_minor'),
        );

        $metadata = (array) $voucher->refresh()->metadata;
        data_set($metadata, 'treasury.pay_code_reservation.status', 'settled');
        data_set($metadata, 'treasury.pay_code_reservation.settled_at', now()->toIso8601String());
        data_set($metadata, 'disbursement.status', 'completed');
        data_set($metadata, 'disbursement.gateway', $reconciliation->provider);
        data_set($metadata, 'disbursement.transaction_id', $reconciliation->provider_transaction_id);
        data_set($metadata, 'disbursement.requires_reconciliation', false);
        $voucher->forceFill(['metadata' => $metadata])->saveQuietly();

        $reconciliation->forceFill(['internal_status' => 'finalized'])->save();

        Log::info('[XChange] Disbursement confirmed', [
            'reconciliation_id' => $reconciliation->id,
            'voucher_code' => $reconciliation->voucher_code,
            'provider_transaction_id' => $reconciliation->provider_transaction_id,
            'status' => $reconciliation->status,
        ]);

        $this->queueTerminalFeedback($voucher, $reconciliation);
    }

    private function queueTerminalFeedback(
        Voucher $voucher,
        DisbursementReconciliation $reconciliation,
    ): void {
        $claim = VoucherClaim::query()
            ->where('voucher_id', $voucher->getKey())
            ->latest('id')
            ->first();

        if ($claim instanceof VoucherClaim) {
            DispatchVoucherRedemptionFeedbackJob::dispatch(
                $claim->getKey(),
                'provider-confirmed:'.(string) $reconciliation->provider_transaction_id,
            )
                ->afterCommit();
        }
    }
}
