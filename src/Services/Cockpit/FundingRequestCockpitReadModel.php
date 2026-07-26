<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Number;
use LBHurtado\XChange\Enums\FundingRequestStatus;
use LBHurtado\XChange\Models\FundingRequest;
use LBHurtado\XChange\Models\FundingRequestNotice;

final readonly class FundingRequestCockpitReadModel
{
    /**
     * @return array<string, mixed>
     */
    public function forOperator(Authenticatable $operator): array
    {
        $actorType = $operator::class;
        $actorId = (string) $operator->getAuthIdentifier();
        $requests = FundingRequest::query()
            ->where('requester_type', $actorType)
            ->where('requester_id', $actorId)
            ->with('voucher.envelope.attachments')
            ->latest('submitted_at')
            ->limit(20)
            ->get();
        $notices = FundingRequestNotice::query()
            ->where('recipient_type', $actorType)
            ->where('recipient_id', $actorId)
            ->latest()
            ->limit(10)
            ->get();

        return [
            'schema' => 'x-change.cockpit.account-funding-requests.v1',
            'requests' => $requests->map(fn (FundingRequest $request): array => [
                'reference' => $request->reference,
                'type' => $request->funding_type->value,
                'type_label' => $request->funding_type->label(),
                'requested_value' => $this->money(
                    $request->requested_value_minor,
                    $request->currency,
                ),
                'recognized_value' => $request->approved_value_minor === null
                    ? null
                    : $this->money($request->approved_value_minor, $request->currency),
                'currency' => $request->currency,
                'status' => $this->status($request->status),
                'receipt_status' => $this->receiptStatus($request->status),
                'receipt_status_label' => $this->receiptStatusLabel(
                    $request->status,
                ),
                'description' => $request->description,
                'submitted_at' => $request->submitted_at?->toIso8601String(),
                'completed_at' => $request->completed_at?->toIso8601String(),
                'evidence' => [
                    'attachment_count' => $request->voucher?->envelope?->attachments->count() ?? 0,
                    'pending_count' => $request->voucher?->envelope?->attachments
                        ->where('review_status', 'pending')
                        ->count() ?? 0,
                    'accepted_count' => $request->voucher?->envelope?->attachments
                        ->where('review_status', 'accepted')
                        ->count() ?? 0,
                    'envelope_status' => $request->voucher?->envelope?->status->value,
                    'documents' => $request->voucher?->envelope?->attachments
                        ->map(fn ($attachment): array => [
                            'id' => (int) $attachment->getKey(),
                            'type' => $attachment->doc_type,
                            'filename' => $attachment->original_filename,
                            'mime_type' => $attachment->mime_type,
                            'size' => (int) $attachment->size,
                            'review_status' => $attachment->review_status,
                            'url' => route(
                                'x-change.cockpit.funding.requests.evidence.show',
                                [$request, $attachment],
                            ),
                        ])->values()->all() ?? [],
                ],
                'pay_code' => $request->voucher === null ? null : [
                    'request_reference' => $request->reference,
                    'code' => $request->voucher->code,
                    'display_code' => $this->displayCode($request),
                    'last_four' => mb_substr($request->voucher->code, -4),
                    'status' => match (true) {
                        $request->status === FundingRequestStatus::Completed => 'account_funded',
                        $request->voucher->isExpired() => 'expired',
                        $request->voucher->voucher_type->value === 'payable'
                            && $request->status === FundingRequestStatus::PayCodeIssued => 'awaiting_system_treasury',
                        $request->voucher->redeemed_at !== null => 'claimed',
                        default => 'locked_pending_review',
                    },
                    'amount' => $this->money(
                        $request->approved_value_minor
                            ?? $request->requested_value_minor,
                        $request->currency,
                    ),
                    'voucher_type' => $request->voucher->voucher_type->value,
                    'collection_mode' => $request->voucher->voucher_type->value === 'payable'
                        ? 'system_treasury'
                        : 'recipient_claim',
                    'can_claim' => $request->voucher->voucher_type->value !== 'payable'
                        && $request->status === FundingRequestStatus::PayCodeIssued
                        && $request->voucher->canRedeem(),
                    'can_copy' => ! in_array($request->status, [
                        FundingRequestStatus::Completed,
                        FundingRequestStatus::Rejected,
                        FundingRequestStatus::Withdrawn,
                        FundingRequestStatus::Expired,
                    ], true),
                    'expires_at' => $request->voucher->expires_at?->toIso8601String(),
                ],
            ])->all(),
            'notices' => $notices->map(fn (FundingRequestNotice $notice): array => [
                'reference' => $notice->reference,
                'type' => $notice->notice_type,
                'title' => $notice->title,
                'message' => $notice->message,
                'action' => $notice->action,
                'read' => $notice->read_at !== null,
                'created_at' => $notice->created_at?->toIso8601String(),
            ])->all(),
            'review_queue' => [],
            'controls' => [
                'attachments_enabled' => (bool) config(
                    'x-change.funding.requests.attachments_enabled',
                    true,
                ),
                'evidence_authorizes_credit' => false,
                'maker_checker_required' => true,
                'reviewer' => false,
                'provider_payout_enabled' => false,
            ],
            'redactions' => [
                'account_references_exposed' => false,
                'code_secret_exposed' => false,
                'reviewed_pay_code_exposed_to_owner' => true,
                'requester_notes_exposed' => false,
                'review_notes_exposed' => false,
            ],
        ];
    }

    private function money(int $minor, string $currency): string
    {
        return Number::currency($minor / 100, in: $currency, locale: 'en_PH');
    }

    private function status(FundingRequestStatus $status): string
    {
        return $status === FundingRequestStatus::PayCodeIssued
            ? 'pay_code_issued'
            : $status->value;
    }

    private function receiptStatus(FundingRequestStatus $status): string
    {
        return match ($status) {
            FundingRequestStatus::Submitted,
            FundingRequestStatus::UnderReview,
            FundingRequestStatus::AwaitingApproval => 'pending',
            FundingRequestStatus::NeedsInformation => 'action_needed',
            FundingRequestStatus::PayCodeIssued => 'funding',
            FundingRequestStatus::Completed => 'funded',
            FundingRequestStatus::Rejected => 'not_funded',
            FundingRequestStatus::Withdrawn => 'cancelled',
            FundingRequestStatus::Expired => 'expired',
        };
    }

    private function receiptStatusLabel(FundingRequestStatus $status): string
    {
        return match ($this->receiptStatus($status)) {
            'pending' => 'Pending',
            'action_needed' => 'Action needed',
            'funding' => 'Adding funds',
            'funded' => 'Funded',
            'not_funded' => 'Not funded',
            'cancelled' => 'Cancelled',
            'expired' => 'Expired',
        };
    }

    private function displayCode(FundingRequest $fundingRequest): string
    {
        if (
            ! in_array($fundingRequest->status, [
                FundingRequestStatus::Completed,
                FundingRequestStatus::Rejected,
                FundingRequestStatus::Withdrawn,
                FundingRequestStatus::Expired,
            ], true)
        ) {
            return (string) $fundingRequest->voucher?->code;
        }

        return '••••'.mb_substr((string) $fundingRequest->voucher?->code, -4);
    }
}
