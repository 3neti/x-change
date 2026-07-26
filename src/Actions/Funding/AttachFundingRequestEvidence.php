<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use LBHurtado\SettlementEnvelope\Models\EnvelopeAttachment;
use LBHurtado\SettlementEnvelope\Services\EnvelopeService;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Enums\FundingRequestStatus;
use LBHurtado\XChange\Models\FundingRequest;
use RuntimeException;

final readonly class AttachFundingRequestEvidence
{
    public function __construct(
        private EnvelopeService $envelopes,
    ) {}

    public function handle(
        FundingRequest $fundingRequest,
        UploadedFile $file,
        string $documentType,
        Model $actor,
    ): EnvelopeAttachment {
        $fundingRequest->loadMissing('voucher.envelope');

        if (
            $fundingRequest->requester_type !== $actor::class
            || $fundingRequest->requester_id !== (string) $actor->getKey()
        ) {
            throw new RuntimeException(
                'Only the Funding Request owner may attach submission evidence.',
            );
        }

        if (! in_array($fundingRequest->status, [
            FundingRequestStatus::Submitted,
            FundingRequestStatus::NeedsInformation,
        ], true)) {
            throw new RuntimeException(
                'Evidence can no longer be attached to this Funding Request.',
            );
        }

        if (
            ! $fundingRequest->voucher instanceof Voucher
            || $fundingRequest->voucher->envelope === null
        ) {
            throw new RuntimeException(
                'The Funding Request Settlement Envelope is unavailable.',
            );
        }

        return $this->envelopes->uploadAttachment(
            envelope: $fundingRequest->voucher->envelope,
            docType: mb_strtoupper(trim($documentType)),
            file: $file,
            actor: $actor,
            metadata: [
                'source' => 'x_change_funding_request_submission',
                'funding_request_reference' => $fundingRequest->reference,
                'scanner' => [
                    'status' => 'not_configured',
                    'extension_point' => 'funding_evidence_scanner',
                ],
            ],
        );
    }
}
