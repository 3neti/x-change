<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\PublicIssuance;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Actions\IssuanceBatch\StartIssuanceBatchRecovery;
use LBHurtado\XChange\Actions\IssuanceBatch\VerifyIssuanceBatchRecovery;
use LBHurtado\XChange\Models\IssuanceBatch;
use LBHurtado\XChange\Services\IssuanceBatch\IssuanceBatchAccess;

final class PublicIssuanceBatchRecoveryController extends Controller
{
    public function request(Request $request, IssuanceBatch $batch, StartIssuanceBatchRecovery $start): JsonResponse
    {
        $validated = $request->validate(['mobile' => ['required', 'string', 'max:32']]);
        $challenge = $start->handle($batch, $validated['mobile']);

        return response()->json([
            'challenge_reference' => $challenge->reference,
            'expires_at' => $challenge->expires_at?->toIso8601String(),
            'message' => 'A recovery code was sent to the mobile number on this batch.',
        ], 202);
    }

    public function verify(Request $request, IssuanceBatch $batch, VerifyIssuanceBatchRecovery $verify, IssuanceBatchAccess $access): JsonResponse
    {
        $validated = $request->validate([
            'challenge_reference' => ['required', 'string', 'max:32'],
            'code' => ['required', 'string', 'max:12'],
        ]);
        $verify->handle($batch, $validated['challenge_reference'], $validated['code']);
        $access->grantSession($request, $batch);

        return response()->json([
            'workspace_url' => route(
                'x-change.public-auto-generate.batches.pay-codes',
                ['batch' => $batch->reference],
                false,
            ),
        ]);
    }
}
