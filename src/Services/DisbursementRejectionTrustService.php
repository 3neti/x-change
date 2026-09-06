<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\XChange\Models\DisbursementReconciliation;

final readonly class DisbursementRejectionTrustService
{
    public function isTrusted(DisbursementReconciliation $reconciliation): bool
    {
        if ($reconciliation->status !== 'failed' || $reconciliation->needs_review) {
            return false;
        }

        if (filled($reconciliation->provider_transaction_id)) {
            return true;
        }

        return $reconciliation->completed_at !== null
            && filled($reconciliation->provider)
            && $reconciliation->provider !== 'unknown'
            && data_get($reconciliation->meta, 'provider_response.received') === true
            && data_get($reconciliation->meta, 'provider_response.status') === 'failed';
    }
}
