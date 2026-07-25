<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Number;
use LBHurtado\XChange\Enums\AccountFundingCodeStatus;
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
            ->with('fundingCode')
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
                'status' => $request->status->value,
                'description' => $request->description,
                'submitted_at' => $request->submitted_at?->toIso8601String(),
                'funding_code' => $request->fundingCode === null ? null : [
                    'reference' => $request->fundingCode->reference,
                    'last_four' => $request->fundingCode->code_last_four,
                    'status' => $request->fundingCode->status->value,
                    'amount' => $this->money(
                        $request->fundingCode->amount_minor,
                        $request->fundingCode->currency,
                    ),
                    'can_claim' => $request->fundingCode->status
                        === AccountFundingCodeStatus::Issued
                        && $request->fundingCode->expires_at?->isFuture() === true,
                    'expires_at' => $request->fundingCode->expires_at?->toIso8601String(),
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
                'attachments_enabled' => false,
                'evidence_authorizes_credit' => false,
                'maker_checker_required' => true,
                'reviewer' => $isReviewer,
                'provider_payout_enabled' => false,
            ],
            'redactions' => [
                'account_references_exposed' => false,
                'code_secret_exposed' => false,
                'requester_notes_exposed' => false,
                'review_notes_exposed' => false,
            ],
        ];
    }

    private function money(int $minor, string $currency): string
    {
        return Number::currency($minor / 100, in: $currency, locale: 'en_PH');
    }
}
