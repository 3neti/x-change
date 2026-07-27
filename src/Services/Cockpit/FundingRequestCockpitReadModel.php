<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Number;
use LBHurtado\XChange\Enums\FundingRequestStatus;
use LBHurtado\XChange\Enums\FundingTransferWindow;
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
            ->with([
                'events',
                'transferMatch',
                'transferAmountReservation',
                'voucher.envelope.attachments',
            ])
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
            'schema' => 'x-change.cockpit.account-funding-requests.v3',
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
                'transfer' => $this->transferSummary($request),
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
            'bank_transfer' => $this->bankTransferInstructions(),
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

    /**
     * @return array<string, mixed>|null
     */
    private function transferSummary(FundingRequest $request): ?array
    {
        if ($request->funding_type->value !== 'bank_transfer') {
            return null;
        }

        $reference = trim((string) $request->external_reference_ciphertext);
        $event = $request->events
            ->whereIn('event_type', [
                'provider_check_awaiting_evidence',
                'provider_check_ambiguous',
                'provider_transfer_verified',
                'provider_transfer_credited',
            ])
            ->last();
        $accountNumber = preg_replace(
            '/\D/',
            '',
            (string) config(
                'payment-gateway.netbank.funding.corporate_account_number',
            ),
        );
        $status = match (true) {
            $request->status === FundingRequestStatus::Completed => 'credited',
            $request->status === FundingRequestStatus::AwaitingApproval
                && $request->transferMatch !== null => 'approval_required',
            $event?->event_type === 'provider_check_ambiguous' => 'review_required',
            $event?->event_type === 'provider_check_awaiting_evidence' => 'awaiting_provider_evidence',
            default => 'ready_to_check',
        };
        $automaticCreditWindowMinutes = max(1, (int) config(
            'x-change.funding.requests.bank_transfer.automatic_credit_window_minutes',
            10,
        ));
        $window = FundingTransferWindow::tryFrom((string) data_get(
            $request->metadata,
            'transfer_window',
            FundingTransferWindow::Recent->value,
        )) ?? FundingTransferWindow::Recent;
        $reservation = $request->transferAmountReservation;

        return [
            'provider' => mb_strtolower((string) config(
                'x-change.funding.requests.bank_transfer.provider',
                'netbank',
            )),
            'target_label' => $accountNumber === ''
                ? 'Configured receiving account'
                : 'NetBank ••••'.mb_substr($accountNumber, -4),
            'reference_hint' => $reference === ''
                ? null
                : '••••'.mb_substr($reference, -4),
            'window' => $window->value,
            'window_label' => $window->label($automaticCreditWindowMinutes),
            'requested_amount' => $this->money(
                $request->requested_value_minor,
                $request->currency,
            ),
            'matching_adjustment' => $reservation === null
                ? null
                : $this->money(
                    $reservation->matching_adjustment_minor,
                    $request->currency,
                ),
            'expected_amount' => $reservation === null
                ? $this->money(
                    $request->requested_value_minor,
                    $request->currency,
                )
                : $this->money(
                    $reservation->expected_amount_minor,
                    $request->currency,
                ),
            'instruction_status' => $reservation?->status->value,
            'instruction_expires_at' => $reservation === null
                ? null
                : $this->storedInstant($reservation, 'expires_at')
                    ->toIso8601String(),
            'full_expected_amount_is_credited' => $reservation !== null,
            'verification_status' => $status,
            'last_checked_at' => $event?->occurred_at?->toIso8601String(),
            'can_check' => in_array($request->status, [
                FundingRequestStatus::Submitted,
                FundingRequestStatus::UnderReview,
            ], true),
            'provider_authority_required' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function bankTransferInstructions(): array
    {
        $automaticCreditWindowMinutes = max(1, (int) config(
            'x-change.funding.requests.bank_transfer.automatic_credit_window_minutes',
            10,
        ));
        $reservedAmountsEnabled = (bool) config(
            'x-change.funding.requests.bank_transfer.reserved_amounts.enabled',
            true,
        );
        $minimumAdjustmentMinor = max(0, (int) config(
            'x-change.funding.requests.bank_transfer.reserved_amounts.minimum_adjustment_minor',
            317,
        ));
        $maximumAdjustmentMinor = max($minimumAdjustmentMinor, (int) config(
            'x-change.funding.requests.bank_transfer.reserved_amounts.maximum_adjustment_minor',
            537,
        ));
        $instructionTtlSeconds = max(60, (int) config(
            'x-change.funding.requests.bank_transfer.reserved_amounts.ttl_seconds',
            600,
        ));

        return [
            'enabled' => (bool) config(
                'x-change.funding.requests.bank_transfer.enabled',
                true,
            ),
            'provider' => mb_strtolower((string) config(
                'x-change.funding.requests.bank_transfer.provider',
                'netbank',
            )),
            'institution' => 'NetBank',
            'account_name' => (string) config(
                'payment-gateway.netbank.funding.corporate_account_name',
                '',
            ),
            'account_number' => (string) config(
                'payment-gateway.netbank.funding.corporate_account_number',
                '',
            ),
            'currency' => 'PHP',
            'reserved_exact_amounts_enabled' => $reservedAmountsEnabled,
            'minimum_adjustment' => $this->money(
                $minimumAdjustmentMinor,
                'PHP',
            ),
            'maximum_adjustment' => $this->money(
                $maximumAdjustmentMinor,
                'PHP',
            ),
            'instruction_valid_for_minutes' => (int) ceil(
                $instructionTtlSeconds / 60,
            ),
            'full_expected_amount_is_credited' => true,
            'automatic_credit_window_minutes' => $automaticCreditWindowMinutes,
            'windows' => collect(FundingTransferWindow::cases())
                ->map(fn (FundingTransferWindow $window): array => [
                    'value' => $window->value,
                    'label' => $window->label($automaticCreditWindowMinutes),
                    'automatic' => $window === FundingTransferWindow::Recent,
                ])
                ->all(),
            'sender_reference_authority' => false,
        ];
    }

    private function storedInstant(
        Model $model,
        string $attribute,
    ): CarbonImmutable {
        return CarbonImmutable::parse(
            (string) $model->getRawOriginal($attribute),
            'UTC',
        );
    }
}
