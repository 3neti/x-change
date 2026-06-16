<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Claim;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\Claim\ClaimApprovalStatusResolver;
use LBHurtado\XChange\Contracts\ClaimApprovalWorkflowStoreContract;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Support\Claim\CompiledClaimResultSession;

final class ClaimApprovalPageController
{
    public function __invoke(Request $request, string $code): Response|JsonResponse|RedirectResponse
    {
        $voucher = Voucher::query()
            ->where('code', strtoupper(trim($code)))
            ->firstOrFail();

        if (! $request->wantsJson()) {
            $providerPayout = $this->submittedProviderPayout($voucher);

            if ($providerPayout instanceof DisbursementReconciliation) {
                $this->putSuccessPayload($voucher, $providerPayout);

                return redirect()->route('x-change.claim.success', [
                    'code' => $voucher->code,
                ]);
            }
        }

        $compiled = app(CompiledClaimResultSession::class)->get();

        if ($compiled === null) {
            $compiled = app(ClaimApprovalStatusResolver::class)
                ->resolve($voucher)
                ?->toCompiledClaimResult();
        }

        if ($compiled === null) {
            $compiled = $this->workflowCompiledClaimResult($voucher);
        }

        $props = [
            'voucher' => [
                'code' => (string) $voucher->code,
                'amount' => data_get($voucher, 'cash.amount'),
                'currency' => data_get($voucher, 'cash.currency'),
            ],
            'compiled_claim_result' => $compiled,
            'approval' => $this->approvalPayload($compiled),
            'approval_entry_mode' => $this->approvalEntryMode($request),
            'message' => 'Your claim has been submitted and is awaiting approval.',
        ];

        if (request()->wantsJson()) {
            return response()->json($props);
        }

        return Inertia::render('x-change/claim/Approval', $props);
    }

    private function approvalPayload(?array $compiled): array
    {
        $metadata = (array) data_get($compiled, 'approval_metadata', []);

        return [
            'required' => (string) data_get($compiled, 'status') === 'approval_required',
            'provider' => data_get($metadata, 'provider'),
            'authorization_type' => data_get($metadata, 'authorization_type'),
            'reference_id' => data_get($metadata, 'reference_id'),
            'otp_required' => (bool) data_get($metadata, 'otp_required', false),
            'message' => data_get($metadata, 'message')
                ?: data_get($compiled, 'messages.0')
                    ?: 'Approval is required to continue.',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function workflowCompiledClaimResult(Voucher $voucher): ?array
    {
        $approval = data_get(app(ClaimApprovalWorkflowStoreContract::class)->get($voucher), 'approval');

        if (! is_array($approval)) {
            return null;
        }

        data_set($approval, 'voucher_code', (string) $voucher->code);

        $metadata = data_get($approval, 'approval_metadata');

        if (! is_array($metadata)) {
            $metadata = data_get($approval, 'meta');
        }

        if (is_array($metadata)) {
            data_set($approval, 'approval_metadata', $metadata);
        }

        if (! is_string(data_get($approval, 'status'))) {
            data_set($approval, 'status', 'approval_required');
        }

        return $approval;
    }

    private function approvalEntryMode(Request $request): string
    {
        if ($request->routeIs('x-change.pay-codes.approval')) {
            return 'issuer_otp_entry';
        }

        return 'redeemer_waiting';
    }

    private function submittedProviderPayout(Voucher $voucher): ?DisbursementReconciliation
    {
        $references = $this->providerReferenceCandidates($voucher);

        if ($references === []) {
            return null;
        }

        return DisbursementReconciliation::query()
            ->where('voucher_code', (string) $voucher->code)
            ->where('provider', 'paynamics')
            ->whereIn('status', ['pending', 'succeeded'])
            ->where(function ($query) use ($references): void {
                $query
                    ->whereIn('provider_reference', $references)
                    ->orWhereIn('provider_transaction_id', $references);
            })
            ->latest('id')
            ->first();
    }

    /**
     * @return array<int, string>
     */
    private function providerReferenceCandidates(Voucher $voucher): array
    {
        $metadata = $voucher->fresh()?->metadata ?? $voucher->metadata;
        $metadata = is_array($metadata) ? $metadata : [];
        $account = data_get($metadata, 'disbursement.recipient_identifier');

        return array_values(array_filter(array_unique([
            data_get($metadata, 'disbursement.reference_id'),
            data_get($metadata, 'disbursement.provider_reference'),
            data_get($metadata, 'disbursement.provider_tx'),
            data_get($metadata, 'disbursement.transaction_id'),
            data_get($metadata, 'disbursement.request_id'),
            is_string($account) && trim($account) !== ''
                ? (string) $voucher->code.'-'.trim($account)
                : null,
        ]), fn (mixed $value): bool => is_string($value) && trim($value) !== ''));
    }

    private function putSuccessPayload(Voucher $voucher, DisbursementReconciliation $providerPayout): void
    {
        app(CompiledClaimResultSession::class)->put((object) [
            'status' => 'redeemed',
            'claim_type' => $providerPayout->claim_type ?: 'redeem',
            'voucher_code' => (string) $voucher->code,
            'claimed' => true,
            'requested_amount' => $providerPayout->amount !== null ? (float) $providerPayout->amount : null,
            'disbursed_amount' => $providerPayout->amount !== null ? (float) $providerPayout->amount : null,
            'currency' => $providerPayout->currency ?: data_get($voucher, 'cash.currency'),
            'remaining_balance' => null,
            'fully_claimed' => true,
            'messages' => [
                'Voucher redemption payout resumed after approval OTP.',
            ],
        ]);
    }
}
