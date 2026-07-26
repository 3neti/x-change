<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LBHurtado\SettlementEnvelope\Services\EnvelopeService;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Data\Funding\PrepareFundingRequestData;
use LBHurtado\XChange\Enums\FundingRequestStatus;
use LBHurtado\XChange\Models\FundingRequest;
use RuntimeException;

final readonly class PrepareFundingRequest
{
    public function __construct(
        private EnvelopeService $envelopes,
    ) {}

    public function handle(
        FundingRequest $fundingRequest,
        PrepareFundingRequestData $data,
    ): FundingRequest {
        if ($data->recognizedValueMinor <= 0) {
            throw new RuntimeException('Recognized backing value must be greater than zero.');
        }

        if (trim($data->evidenceReference) === '') {
            throw new RuntimeException('Independent backing evidence is required.');
        }

        return DB::transaction(function () use ($fundingRequest, $data): FundingRequest {
            $locked = FundingRequest::query()
                ->with('voucher.envelope')
                ->lockForUpdate()
                ->findOrFail($fundingRequest->getKey());

            if (! in_array($locked->status, [
                FundingRequestStatus::Submitted,
                FundingRequestStatus::UnderReview,
                FundingRequestStatus::NeedsInformation,
            ], true)) {
                throw new RuntimeException('This Funding Request is not reviewable.');
            }

            if (mb_strtoupper(trim($data->currency)) !== $locked->currency) {
                throw new RuntimeException('Recognized backing currency must match the request.');
            }

            $fromStatus = $locked->status;
            $nextVersion = $locked->version + 1;
            $reviewer = $this->actor($data->reviewerType, $data->reviewerId);

            if (
                ! $locked->voucher instanceof Voucher
                || $locked->voucher->envelope === null
            ) {
                throw new RuntimeException(
                    'Reviewed Account Funding requires its Settlement Envelope.',
                );
            }

            $this->envelopes->setSignal(
                $locked->voucher->envelope,
                'backing_verified',
                true,
                $reviewer,
            );
            foreach (
                $locked->voucher->envelope->attachments()
                    ->where('review_status', 'pending')
                    ->get() as $attachment
            ) {
                $this->envelopes->reviewAttachment(
                    $attachment,
                    'accepted',
                    $reviewer,
                );
            }
            $locked->forceFill([
                'approved_value_minor' => $data->recognizedValueMinor,
                'status' => FundingRequestStatus::AwaitingApproval,
                'version' => $nextVersion,
                'evidence_reference' => trim($data->evidenceReference),
                'connection_reference' => trim($data->connectionReference),
                'review_notes_ciphertext' => $data->reviewNotes,
                'reviewed_by_type' => $data->reviewerType,
                'reviewed_by_id' => $data->reviewerId,
                'reviewed_at' => now(),
            ])->saveQuietly();

            $locked->events()->create([
                'sequence' => $nextVersion,
                'event_type' => 'backing_prepared_for_approval',
                'from_status' => $fromStatus,
                'to_status' => FundingRequestStatus::AwaitingApproval,
                'actor_type' => $data->reviewerType,
                'actor_id' => $data->reviewerId,
                'evidence_reference' => trim($data->evidenceReference),
                'metadata' => [
                    'recognized_value_minor' => $data->recognizedValueMinor,
                    'currency' => $locked->currency,
                    'browser_supplied_credit_authority' => false,
                ],
                'occurred_at' => now(),
            ]);

            return $locked->refresh()->load('events');
        }, 3);
    }

    private function actor(string $type, string $id): Model
    {
        if (! is_subclass_of($type, Model::class)) {
            throw new RuntimeException('Funding Request reviewer type is invalid.');
        }

        $actor = $type::query()->find($id);

        if (! $actor instanceof Model) {
            throw new RuntimeException('Funding Request reviewer was not found.');
        }

        return $actor;
    }
}
