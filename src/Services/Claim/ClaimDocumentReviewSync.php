<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Claim;

use LBHurtado\FormHandlerDocument\Models\DocumentSubmission;
use LBHurtado\SettlementEnvelope\Events\AttachmentReviewed;

final class ClaimDocumentReviewSync
{
    public function handle(AttachmentReviewed $event): void
    {
        if ($event->decision !== 'rejected'
            || $event->attachment->doc_type !== 'CLAIM_DOCUMENT'
            || ! is_string($event->attachment->document_id)) {
            return;
        }
        DocumentSubmission::query()->where('reference', $event->attachment->document_id)
            ->where('status', 'linked')->update(['status' => 'rejected', 'updated_at' => now()]);
    }
}
