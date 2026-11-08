<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Claim;

use Illuminate\Support\Facades\DB;
use LBHurtado\FormFlowManager\Services\FormFlowService;
use LBHurtado\FormHandlerDocument\Events\DocumentSubmitted;
use LBHurtado\FormHandlerDocument\Models\DocumentSubmission;
use LBHurtado\SettlementEnvelope\Models\Envelope;
use LBHurtado\SettlementEnvelope\Services\EnvelopeService;
use LBHurtado\Voucher\Models\Voucher;

final class ClaimDocumentEnvelopeBridge
{
    public function __construct(
        private readonly ClaimDocumentAccess $access,
        private readonly FormFlowService $flows,
        private readonly EnvelopeService $envelopes,
    ) {}

    public function handle(DocumentSubmitted $event): void
    {
        $request = request();
        $scope = $this->access->authorizeUpload($request, $event->flowId, $event->submission->document_key);
        $state = $this->flows->getFlowState($event->flowId);
        $code = data_get($state, 'instructions.metadata.voucher_code');
        abort_unless(is_string($code), 404);
        $voucher = Voucher::query()->where('code', $code)->firstOrFail();
        $definition = collect((array) data_get($voucher->metadata, 'instructions.inputs.documents', []))
            ->first(fn (mixed $item): bool => is_array($item) && ($item['key'] ?? null) === $scope->documentKey);
        $envelope = $voucher->envelope()->firstOrFail();

        DB::transaction(function () use ($event, $scope, $envelope, $definition): void {
            $envelope = Envelope::query()->lockForUpdate()->findOrFail($envelope->getKey());
            $submission = DocumentSubmission::query()->lockForUpdate()->findOrFail($event->submission->getKey());
            abort_unless(hash_equals($scope->flowHash, $submission->flow_hash)
                && hash_equals($scope->participantHash, $submission->participant_hash)
                && $scope->documentKey === $submission->document_key
                && in_array($submission->status, ['staged', 'linked'], true), 404);
            $media = $submission->getFirstMedia('documents');
            abort_unless($media !== null, 404);
            $this->envelopes->attachStoredAttachment($envelope, [
                'document_id' => $submission->reference,
                'doc_type' => 'CLAIM_DOCUMENT',
                'original_filename' => $media->file_name,
                'disk' => $media->disk,
                'file_path' => $media->getPathRelativeToRoot(),
                'mime_type' => $submission->mime_type,
                'size' => (int) $submission->size,
                'hash' => $submission->sha256,
                'metadata' => [
                    'contributor_role' => 'claimant',
                    'claim_document_key' => $scope->documentKey,
                    'label' => $definition['label'] ?? $scope->documentKey,
                ],
            ]);
            if ($submission->status !== 'linked') {
                $submission->forceFill(['status' => 'linked', 'linked_at' => now()])->save();
            }
        });
    }
}
