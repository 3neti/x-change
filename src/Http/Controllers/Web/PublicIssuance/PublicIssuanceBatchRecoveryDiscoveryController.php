<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\PublicIssuance;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LBHurtado\FormHandlerOtp\Contracts\OtpChallengeGateway;
use LBHurtado\FormHandlerOtp\Data\OtpChallengeRequestData;
use LBHurtado\FormHandlerOtp\Data\OtpVerificationProofData;
use LBHurtado\XChange\Models\IssuanceBatch;
use LBHurtado\XChange\Models\MobileVerificationChallenge;
use LBHurtado\XChange\Services\IssuanceBatch\IssuanceBatchAccess;
use LBHurtado\XChange\Support\Auth\MobileNumber;
use Throwable;

final class PublicIssuanceBatchRecoveryDiscoveryController extends Controller
{
    public function __construct(private readonly OtpChallengeGateway $otp) {}

    public function request(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mobile' => ['required', 'string', 'max:32'],
            'telemetry' => ['sometimes', 'array'],
        ]);
        $candidates = MobileNumber::candidates($validated['mobile']);
        $hashes = array_values(array_unique([
            ...array_map(static fn (string $mobile): string => hash('sha256', $mobile), $candidates),
            hash('sha256', trim($validated['mobile'])),
        ]));
        $batches = IssuanceBatch::query()->whereIn('uploader_mobile_hash', $hashes)->get();

        if ($batches->isEmpty()) {
            return response()->json(['message' => 'If matching workspaces exist, a recovery code will be sent.'], 202);
        }

        $reference = (string) Str::ulid();
        $normalized = MobileNumber::normalize($validated['mobile']);
        $matchingHashes = $batches->pluck('uploader_mobile_hash')->filter()->unique()->values()->all();
        $challenge = DB::transaction(function () use ($reference, $normalized, $matchingHashes): MobileVerificationChallenge {
            return MobileVerificationChallenge::query()->create([
                'reference' => $reference,
                'user_type' => self::class,
                'user_id' => json_encode($matchingHashes, JSON_THROW_ON_ERROR),
                'mobile_hash' => hash('sha256', (string) $normalized),
                'provider' => (string) config('x-change.onboarding.identity_otp.driver', 'unavailable'),
                'purpose' => 'public.batch-recovery-discovery',
                'status' => 'delivery_pending',
                'attempts' => 0,
                'expires_at' => now()->addMinutes(10),
            ]);
        });

        try {
            $providerChallenge = $this->otp->create(new OtpChallengeRequestData(
                mobile: '+'.(string) $normalized,
                purpose: 'public.batch-recovery-discovery',
                client_reference: $reference,
            ));
            $challenge->forceFill([
                'provider_challenge_reference' => $providerChallenge->reference,
                'status' => 'pending',
                'expires_at' => now()->addSeconds(max(1, $providerChallenge->expires_in)),
            ])->save();
        } catch (Throwable $exception) {
            $challenge->forceFill(['status' => 'delivery_failed'])->save();
            throw $exception;
        }

        return response()->json([
            'challenge_reference' => $challenge->reference,
            'expires_at' => $challenge->expires_at?->toIso8601String(),
            'message' => 'A recovery code was sent to the mobile number on your matching workspaces.',
        ], 202);
    }

    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'challenge_reference' => ['required', 'string', 'max:32'],
            'code' => ['required', 'string', 'max:12'],
            'telemetry' => ['sometimes', 'array'],
        ]);
        $challenge = MobileVerificationChallenge::query()
            ->where('reference', $validated['challenge_reference'])
            ->where('user_type', self::class)
            ->first();

        if ($challenge === null) {
            throw ValidationException::withMessages(['code' => 'The recovery code is invalid or expired.']);
        }

        Cache::lock('x-change:batch-recovery-discovery:'.$challenge->reference, 30)
            ->block(5, fn () => $this->verifyChallenge($challenge, $validated['code']));

        $batches = IssuanceBatch::query()
            ->whereIn('uploader_mobile_hash', $this->challengeHashes($challenge))
            ->latest('created_at')
            ->limit(25)
            ->get()
            ->map(fn (IssuanceBatch $batch): array => [
                'reference' => $batch->reference,
                'created_at' => $batch->created_at?->toIso8601String(),
                'status' => (string) $batch->status->value,
                'item_count' => $batch->item_count,
                'total_minor' => $batch->total_minor,
                'currency' => $batch->currency,
                'issued_at' => $batch->issued_at?->toIso8601String(),
                'expires_at' => $batch->expires_at?->toIso8601String(),
            ])->values();

        return response()->json(['batches' => $batches]);
    }

    public function select(Request $request, IssuanceBatchAccess $access): JsonResponse
    {
        $validated = $request->validate([
            'challenge_reference' => ['required', 'string', 'max:32'],
            'batch_reference' => ['required', 'string', 'max:32'],
        ]);
        $challenge = MobileVerificationChallenge::query()
            ->where('reference', $validated['challenge_reference'])
            ->where('user_type', self::class)
            ->where('status', 'verified')
            ->first();
        $batch = IssuanceBatch::query()->where('reference', $validated['batch_reference'])->first();

        abort_unless($challenge !== null && $challenge->expires_at?->isFuture() && $batch !== null, 404);
        abort_unless(in_array((string) $batch->uploader_mobile_hash, $this->challengeHashes($challenge), true), 404);

        $access->grantSession($request, $batch);

        return response()->json([
            'workspace_url' => route('x-change.public-auto-generate.batches.pay-codes', ['batch' => $batch->reference], false),
        ]);
    }

    private function verifyChallenge(MobileVerificationChallenge $challenge, string $code): void
    {
        $challenge->refresh();
        if (! in_array($challenge->status, ['pending', 'expired'], true) || $challenge->expires_at?->isPast()) {
            throw ValidationException::withMessages(['code' => 'The recovery code is invalid or expired.']);
        }
        if ($challenge->attempts >= 5) {
            $challenge->forceFill(['status' => 'locked'])->save();
            throw ValidationException::withMessages(['code' => 'Too many attempts. Request a new code.']);
        }
        $providerReference = trim((string) $challenge->provider_challenge_reference);
        $result = $providerReference === '' ? null : $this->otp->verify($providerReference, $code);
        if ($result === null || ! $result->ok || ! $result->proof instanceof OtpVerificationProofData) {
            $attempts = $challenge->attempts + 1;
            $challenge->forceFill(['attempts' => $attempts, 'status' => $attempts >= 5 ? 'locked' : 'pending'])->save();
            throw ValidationException::withMessages(['code' => 'The recovery code is invalid or expired.']);
        }
        $challenge->forceFill(['status' => 'verified', 'verified_at' => now(), 'provider_verified_at' => now()])->save();
    }

    /** @return array<int, string> */
    private function challengeHashes(MobileVerificationChallenge $challenge): array
    {
        $hashes = json_decode((string) $challenge->user_id, true);

        return is_array($hashes) ? array_values(array_filter($hashes, 'is_string')) : [(string) $challenge->mobile_hash];
    }
}
