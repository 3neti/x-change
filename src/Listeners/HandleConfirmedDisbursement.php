<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Listeners;

use Illuminate\Support\Facades\Log;
use LBHurtado\XChange\Events\DisbursementConfirmed;

class HandleConfirmedDisbursement
{
    public function handle(DisbursementConfirmed $event): void
    {
        Log::info('[XChange] Disbursement confirmed', [
            'reconciliation_id' => $event->reconciliation->id,
            'voucher_code' => $event->reconciliation->voucher_code,
            'provider_transaction_id' => $event->reconciliation->provider_transaction_id,
            'status' => $event->reconciliation->status,
        ]);

        // Later:
        // - update voucher metadata if desired
        // - notify issuer/redeemer
        // - emit webhooks
        // - mark internal_status finalized
    }
}
