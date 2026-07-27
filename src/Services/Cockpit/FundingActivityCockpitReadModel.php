<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;
use LBHurtado\EmiCore\Enums\FundingAddressPurpose;
use LBHurtado\XChange\Actions\Funding\ReadNetbankReusableFundingReceiptHistory;
use LBHurtado\XChange\Data\Funding\NetbankReusableFundingObservationData;
use LBHurtado\XChange\Models\StandingFundingAddress;

final readonly class FundingActivityCockpitReadModel
{
    public function __construct(
        private ReadNetbankReusableFundingReceiptHistory $standingFundingHistory,
    ) {}

    /**
     * @param  array<string, mixed>  $fundingRequests
     * @return array<string, mixed>
     */
    public function forOperator(
        Authenticatable $operator,
        array $fundingRequests,
    ): array {
        $requestItems = collect($fundingRequests['requests'] ?? [])
            ->filter(fn (mixed $request): bool => is_array($request))
            ->map(fn (array $request): array => $this->requestItem($request));
        $receiptItems = $operator instanceof Model
            ? $this->standingReceiptItems($operator)
            : collect();

        return [
            'schema' => 'x-change.cockpit.funding-activity.v1',
            'items' => $requestItems
                ->concat($receiptItems)
                ->sortByDesc('updated_at')
                ->values()
                ->all(),
            'filters' => [
                ['key' => 'all', 'label' => 'All'],
                ['key' => 'qr_ph', 'label' => 'QR Ph'],
                ['key' => 'bank_transfer', 'label' => 'Bank Transfer'],
                ['key' => 'pay_code', 'label' => 'Pay Code'],
                ['key' => 'reviewed_value', 'label' => 'Reviewed Value'],
            ],
            'redactions' => [
                'payer_identity_exposed' => false,
                'provider_transaction_id_exposed' => false,
                'raw_evidence_exposed' => false,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $request
     * @return array<string, mixed>
     */
    private function requestItem(array $request): array
    {
        $isBankTransfer = ($request['type'] ?? null) === 'bank_transfer';
        $payCode = is_array($request['pay_code'] ?? null)
            ? $request['pay_code']
            : null;
        $transfer = is_array($request['transfer'] ?? null)
            ? $request['transfer']
            : null;
        $method = $isBankTransfer ? 'bank_transfer' : 'reviewed_value';
        $status = $this->requestStatus(
            (string) ($request['receipt_status'] ?? ''),
            $isBankTransfer,
        );
        $actions = collect([
            $transfer !== null ? 'view_instructions' : null,
            ($transfer['can_check'] ?? false) === true ? 'check_provider' : null,
            ($payCode['can_copy'] ?? false) === true ? 'copy_pay_code' : null,
        ])->filter()->values()->all();

        return [
            'key' => 'request:'.(string) ($request['reference'] ?? ''),
            'source' => 'funding_request',
            'reference' => (string) ($request['reference'] ?? ''),
            'display_reference' => (string) ($payCode['display_code']
                ?? $request['reference']
                ?? ''),
            'method' => $method,
            'method_label' => $isBankTransfer ? 'Bank Transfer' : 'Reviewed Value',
            'amount' => (string) ($transfer['expected_amount']
                ?? $request['recognized_value']
                ?? $request['requested_value']
                ?? '—'),
            'status' => $status,
            'status_label' => $this->statusLabel($status),
            'updated_at' => $request['completed_at']
                ?? $transfer['last_checked_at']
                ?? $request['submitted_at']
                ?? null,
            'timestamps' => [
                'requested_at' => $request['submitted_at'] ?? null,
                'observed_at' => null,
                'recognized_at' => $request['completed_at'] ?? null,
            ],
            'summary' => $isBankTransfer
                ? (string) ($transfer['target_label'] ?? 'Configured receiving account')
                : (string) ($request['type_label'] ?? 'Reviewed value'),
            'action_keys' => $actions,
            'request_reference' => (string) ($request['reference'] ?? ''),
            'pay_code' => $payCode,
            'transfer' => $transfer,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function standingReceiptItems(Model $operator): Collection
    {
        $address = StandingFundingAddress::query()
            ->whereMorphedTo('owner', $operator)
            ->where('provider_code', 'netbank')
            ->where('purpose', FundingAddressPurpose::AccountFunding)
            ->first();

        if (! $address instanceof StandingFundingAddress) {
            return collect();
        }

        return collect($this->standingFundingHistory->forAddress($address)->observations)
            ->map(fn (NetbankReusableFundingObservationData $receipt): array => $this->standingReceiptItem($receipt));
    }

    /**
     * @return array<string, mixed>
     */
    private function standingReceiptItem(
        NetbankReusableFundingObservationData $receipt,
    ): array {
        $status = match (true) {
            $receipt->applied => 'recognized',
            $receipt->recognitionStatus === 'awaiting_approval' => 'under_review',
            $receipt->recognitionStatus === 'suspense' => 'needs_attention',
            $receipt->recognitionStatus === 'reversed' => 'reversed',
            default => 'checking_provider',
        };

        return [
            'key' => 'standing_receipt:'.$receipt->reference,
            'source' => 'standing_funding_receipt',
            'reference' => $receipt->reference,
            'display_reference' => $receipt->reference,
            'method' => 'qr_ph',
            'method_label' => 'QR Ph',
            'amount' => Number::currency(
                $receipt->grossAmountMinor / 100,
                in: $receipt->currency,
                locale: 'en_PH',
            ),
            'status' => $status,
            'status_label' => $this->statusLabel($status),
            'updated_at' => $receipt->appliedAt
                ?? $receipt->providerSettledAt
                ?? $receipt->occurredAt,
            'timestamps' => [
                'requested_at' => null,
                'observed_at' => $receipt->occurredAt,
                'recognized_at' => $receipt->appliedAt,
            ],
            'summary' => 'NetBank '.str_replace('_', ' ', $receipt->providerStatus),
            'action_keys' => $receipt->canApprove ? ['approve_receipt'] : [],
            'approval_reference' => $receipt->approvalReference,
            'provisional' => $receipt->provisional,
        ];
    }

    private function requestStatus(
        string $receiptStatus,
        bool $isBankTransfer,
    ): string {
        return match ($receiptStatus) {
            'pending' => $isBankTransfer ? 'awaiting_payment' : 'under_review',
            'action_needed' => 'needs_attention',
            'funding' => $isBankTransfer ? 'processing' : 'pay_code_ready',
            'funded' => 'recognized',
            'not_funded' => 'declined',
            'cancelled' => 'cancelled',
            'expired' => 'expired',
            default => 'under_review',
        };
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'awaiting_payment' => 'Awaiting payment',
            'checking_provider' => 'Checking provider',
            'under_review' => 'Under review',
            'pay_code_ready' => 'Pay Code ready',
            'processing' => 'Processing',
            'recognized' => 'Recognized',
            'needs_attention' => 'Needs attention',
            'declined' => 'Declined',
            'expired' => 'Expired',
            'cancelled' => 'Cancelled',
            'reversed' => 'Reversed',
            default => 'Under review',
        };
    }
}
