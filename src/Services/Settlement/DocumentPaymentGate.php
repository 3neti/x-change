<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Settlement;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Exceptions\VoucherRequiresSettlementEnvelope;

final readonly class DocumentPaymentGate
{
    public function __construct(private DocumentPaymentReadiness $readiness) {}

    public function ensureReady(Voucher $voucher, bool $lockEnvelope = false): void
    {
        $requirements = data_get($voucher->metadata, 'instructions.execution.document_requirements');

        if ($requirements === null) {
            return;
        }

        if (! is_array($requirements) || ! array_is_list($requirements)) {
            throw new VoucherRequiresSettlementEnvelope(voucher: $voucher, message: 'Document requirements are invalid.');
        }

        $query = $voucher->envelope();
        if ($lockEnvelope) {
            $query->lockForUpdate();
        }
        $envelope = $query->first();
        $documents = $envelope?->attachments()->get(['doc_type', 'review_status', 'metadata'])
            ->map(fn ($attachment): array => [
                'type' => $attachment->doc_type,
                'review_status' => $attachment->review_status,
                'contributor' => data_get($attachment->metadata, 'contributor_role'),
            ])->all() ?? [];

        try {
            $missing = $this->readiness->missingForPayment($requirements, $documents);
        } catch (\InvalidArgumentException|\TypeError) {
            throw new VoucherRequiresSettlementEnvelope(voucher: $voucher, message: 'Document requirements are invalid.');
        }

        if ($missing !== []) {
            throw new VoucherRequiresSettlementEnvelope(voucher: $voucher, message: 'Required documents are not ready for payment.');
        }
    }
}
