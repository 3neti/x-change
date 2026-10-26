<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\PublicOnboarding;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use LBHurtado\PaymentGateway\Exceptions\NetbankFundingRequestFailed;
use LBHurtado\XChange\Enums\FundingIntentStatus;
use LBHurtado\XChange\Enums\FundingVerificationTrigger;
use LBHurtado\XChange\Jobs\Funding\VerifyFundingIntentJob;
use LBHurtado\XChange\Models\PublicAutoRegistration;
use LBHurtado\XChange\Services\PublicAutoRegistrationService;

final class AutoRegisterMutationController extends Controller
{
    public function start(Request $request, PublicAutoRegistrationService $registrations): JsonResponse
    {
        $operator = $request->user();
        abort_unless($operator instanceof Model, 503, 'Registration is temporarily unavailable.');
        $request->validate(['idempotency_key' => ['nullable', 'string', 'max:128']]);
        $request->attributes->set('x_change_public_auto_register', true);
        try {
            $registration = $registrations->start($operator, (string) ($request->input('idempotency_key') ?: Str::ulid()), $request);
        } catch (NetbankFundingRequestFailed $exception) {
            report($exception);

            return response()->json([
                'message' => 'Payment setup is temporarily unavailable. Please retry this registration. No payment has been requested.',
            ], 503);
        }

        return response()->json($registrations->present($registration), 201);
    }

    public function status(PublicAutoRegistration $registration, PublicAutoRegistrationService $registrations): JsonResponse
    {
        $registration->load('fundingIntent.settlement.providerFundingObservation');
        $intent = $registration->fundingIntent;

        if ($intent !== null && in_array($intent->status, [FundingIntentStatus::AwaitingFunds, FundingIntentStatus::EvidenceReceived, FundingIntentStatus::Verifying], true)) {
            $dispatchKey = 'x-change:public-auto-register:verify:'.$intent->getKey();

            if (Cache::add($dispatchKey, true, now()->addSeconds(5))) {
                VerifyFundingIntentJob::dispatch(
                    fundingIntentId: $intent->getKey(),
                    providerCode: $intent->provider_code,
                    trigger: FundingVerificationTrigger::Schedule,
                    actorId: 'public-auto-register-monitor',
                )->afterCommit();
            }
        }

        return response()->json($registrations->present($registration->fresh(['fundingIntent.settlement.providerFundingObservation'])));
    }

    public function complete(Request $request, PublicAutoRegistration $registration, PublicAutoRegistrationService $registrations): JsonResponse
    {
        $request->validate(['pin' => ['required', 'digits_between:4,8']]);
        $registration = $registrations->complete($registration, (string) $request->string('pin'));

        return response()->json($registrations->present($registration), 200);
    }
}
