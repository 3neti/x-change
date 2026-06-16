<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Claim;

use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\Claim\ClaimApprovalStatusResolver;
use LBHurtado\XChange\Support\Claim\CompiledClaimResultSession;

final class ClaimApprovalPageController
{
    public function __invoke(string $code): Response|JsonResponse
    {
        $voucher = Voucher::query()
            ->where('code', strtoupper(trim($code)))
            ->firstOrFail();

        $compiled = app(CompiledClaimResultSession::class)->get()
            ?? app(ClaimApprovalStatusResolver::class)->resolve($voucher);

        $props = [
            'voucher' => [
                'code' => (string) $voucher->code,
                'amount' => data_get($voucher, 'cash.amount'),
                'currency' => data_get($voucher, 'cash.currency'),
            ],
            'compiled_claim_result' => $compiled,
            'approval' => $this->approvalPayload($compiled),
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
}
