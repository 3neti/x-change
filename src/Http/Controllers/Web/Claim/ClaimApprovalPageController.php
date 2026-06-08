<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Claim;

use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Support\Claim\CompiledClaimSuccessPayload;

final class ClaimApprovalPageController
{
    public function __invoke(string $code): Response|JsonResponse
    {
        $voucher = Voucher::query()
            ->where('code', strtoupper(trim($code)))
            ->firstOrFail();

        $props = [
            'voucher' => [
                'code' => (string) $voucher->code,
                'amount' => data_get($voucher, 'cash.amount'),
                'currency' => data_get($voucher, 'cash.currency'),
            ],
            'compiled_claim_result' => app(CompiledClaimSuccessPayload::class)->pull(),
            'message' => 'Your claim has been submitted and is awaiting approval.',
        ];

        if (request()->wantsJson()) {
            return response()->json($props);
        }

        return Inertia::render('x-change/claim/Approval', $props);
    }
}
