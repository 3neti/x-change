<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Number;
use LBHurtado\XChange\Enums\FundingRequestStatus;
use LBHurtado\XChange\Models\FundingRequest;
use LBHurtado\XChange\Models\FundingRequestNotice;
use LBHurtado\XChange\Services\Funding\FundingRequestAccess;

final readonly class FundingRequestCockpitReadModel
{
    public function __construct(
        private FundingRequestAccess $access,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forOperator(Authenticatable $operator): array
    {
        $actorType = $operator::class;
        $actorId = (string) $operator->getAuthIdentifier();
        $isReviewer = $this->access->isReviewer($operator);
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
                'description' => $request->description,
                'submitted_at' => $request->submitted_at?->toIso8601String(),
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
                        ->map(static fn ($attachment): array => [
                            'id' => (int) $attachment->getKey(),
                            'type' => $attachment->doc_type,
                            'filename' => $attachment->original_filename,
                            'mime_type' => $attachment->mime_type,
                            'size' => (int) $attachment->size,
                            'review_status' => $attachment->review_status,
                        ])->values()->all() ?? [],
                ],
                'pay_code' => $request->voucher === null ? null : [
                    'request_reference' => $request->reference,
                    'code' => $request->voucher->code,
                    'last_four' => mb_substr($request->voucher->code, -4),
                    'status' => $request->voucher->redeemed_at !== null
                        ? 'claimed'
                        : ($request->voucher->isExpired() ? 'expired' : 'issued'),
                    'amount' => $this->money(
                        (int) round(
                            (float) $request->voucher->instructions->cash->amount * 100,
                        ),
                        $request->voucher->instructions->cash->currency,
                    ),
                    'can_claim' => $request->status === FundingRequestStatus::PayCodeIssued
                        && $request->voucher->canRedeem(),
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
            'review_queue' => $isReviewer
                ? FundingRequest::query()
                    ->whereIn('status', [
                        FundingRequestStatus::Submitted,
                        FundingRequestStatus::NeedsInformation,
                        FundingRequestStatus::AwaitingApproval,
                    ])
                    ->with('voucher.envelope.attachments')
                    ->latest('submitted_at')
                    ->limit(50)
                    ->get()
                    ->map(fn (FundingRequest $request): array => [
                        'reference' => $request->reference,
                        'type' => $request->funding_type->value,
                        'type_label' => $request->funding_type->label(),
                        'requested_value' => $this->money(
                            $request->requested_value_minor,
                            $request->currency,
                        ),
                        'recognized_value' => $request->approved_value_minor === null
                            ? null
                            : $this->money(
                                $request->approved_value_minor,
                                $request->currency,
                            ),
                        'requested_value_minor' => $request->requested_value_minor,
                        'currency' => $request->currency,
                        'status' => $request->status->value,
                        'description' => $request->description,
                        'evidence_reference' => $request->evidence_reference,
                        'connection_reference' => $request->connection_reference,
                        'maker_id' => $request->reviewed_by_id,
                        'evidence' => [
                            'attachment_count' => $request->voucher?->envelope?->attachments->count() ?? 0,
                            'documents' => $request->voucher?->envelope?->attachments
                                ->map(static fn ($attachment): array => [
                                    'id' => (int) $attachment->getKey(),
                                    'type' => $attachment->doc_type,
                                    'filename' => $attachment->original_filename,
                                    'mime_type' => $attachment->mime_type,
                                    'size' => (int) $attachment->size,
                                    'review_status' => $attachment->review_status,
                                ])->values()->all() ?? [],
                        ],
                        'can_prepare' => in_array($request->status, [
                            FundingRequestStatus::Submitted,
                            FundingRequestStatus::NeedsInformation,
                        ], true),
                        'can_approve' => $request->status
                            === FundingRequestStatus::AwaitingApproval
                            && $request->reviewed_by_id !== $actorId,
                    ])->all()
                : [],
            'controls' => [
                'attachments_enabled' => (bool) config(
                    'x-change.funding.requests.attachments_enabled',
                    true,
                ),
                'evidence_authorizes_credit' => false,
                'maker_checker_required' => true,
                'reviewer' => $isReviewer,
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
}
