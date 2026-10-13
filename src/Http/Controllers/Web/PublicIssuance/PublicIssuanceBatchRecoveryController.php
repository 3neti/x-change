<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\PublicIssuance;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use LBHurtado\XChange\Actions\IssuanceBatch\StartIssuanceBatchRecovery;
use LBHurtado\XChange\Actions\IssuanceBatch\VerifyIssuanceBatchRecovery;
use LBHurtado\XChange\Models\IssuanceBatch;
use LBHurtado\XChange\Models\MobileVerificationChallenge;
use LBHurtado\XChange\Services\IssuanceBatch\IssuanceBatchAccess;
use LBHurtado\XChange\Services\IssuanceBatch\IssuanceBatchRecoveryJournal;
use Throwable;

final class PublicIssuanceBatchRecoveryController extends Controller
{
    public function request(Request $request, IssuanceBatch $batch, StartIssuanceBatchRecovery $start, IssuanceBatchRecoveryJournal $journal): JsonResponse
    {
        try {
            $validated = $request->validate([
                'mobile' => ['required', 'string', 'max:32'],
                'telemetry' => ['sometimes', 'array'],
            ]);
        } catch (Throwable $exception) {
            $journal->record($batch, 'issuance_batch.recovery.request_rejected', $request, details: [
                'outcome' => 'rejected',
                'reason' => 'invalid_request',
            ]);

            throw $exception;
        }

        try {
            $challenge = $start->handle($batch, $validated['mobile']);
        } catch (Throwable $exception) {
            $journal->record($batch, 'issuance_batch.recovery.request_rejected', $request, details: [
                'outcome' => 'rejected',
                'reason' => $exception instanceof ValidationException ? 'mobile_mismatch' : 'otp_delivery_failed',
            ]);

            throw $exception;
        }

        $journal->record($batch, 'issuance_batch.recovery.otp_requested', $request, $challenge, [
            'outcome' => 'accepted',
        ]);

        return response()->json([
            'challenge_reference' => $challenge->reference,
            'expires_at' => $challenge->expires_at?->toIso8601String(),
            'message' => 'A recovery code was sent to the mobile number on this batch.',
        ], 202);
    }

    public function verify(Request $request, IssuanceBatch $batch, VerifyIssuanceBatchRecovery $verify, IssuanceBatchAccess $access, IssuanceBatchRecoveryJournal $journal): JsonResponse
    {
        try {
            $validated = $request->validate([
                'challenge_reference' => ['required', 'string', 'max:32'],
                'code' => ['required', 'string', 'max:12'],
                'telemetry' => ['sometimes', 'array'],
            ]);
        } catch (Throwable $exception) {
            $journal->record($batch, 'issuance_batch.recovery.otp_rejected', $request, details: [
                'outcome' => 'rejected',
                'reason' => 'invalid_request',
            ]);

            throw $exception;
        }

        $challenge = MobileVerificationChallenge::query()
            ->where('reference', $validated['challenge_reference'])
            ->where('user_type', IssuanceBatch::class)
            ->where('user_id', (string) $batch->getKey())
            ->first();

        try {
            $challenge = $verify->handle($batch, $validated['challenge_reference'], $validated['code']);
        } catch (Throwable $exception) {
            $journal->record($batch, 'issuance_batch.recovery.otp_rejected', $request, $challenge, [
                'outcome' => 'rejected',
                'reason' => 'invalid_or_expired_code',
                'attempts' => $challenge?->attempts,
            ]);

            throw $exception;
        }

        $access->grantSession($request, $batch);
        $journal->record($batch, 'issuance_batch.recovery.session_granted', $request, $challenge, [
            'outcome' => 'verified',
            'session_ttl_minutes' => 30,
        ]);

        return response()->json([
            'workspace_url' => route(
                'x-change.public-auto-generate.batches.pay-codes',
                ['batch' => $batch->reference],
                false,
            ),
        ]);
    }
}
