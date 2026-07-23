<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Onboarding;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\XChange\Models\MobileVerificationChallenge;
use LBHurtado\XChange\Support\Auth\MobileNumber;

final class MobileVerificationPageController extends Controller
{
    /**
     * @throws AuthenticationException
     */
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        if (! $user instanceof Model) {
            throw new AuthenticationException;
        }

        $mobile = MobileNumber::normalize(
            is_string($user->getRawOriginal('mobile'))
                ? $user->getRawOriginal('mobile')
                : null,
        );
        $challenge = MobileVerificationChallenge::query()
            ->where('user_type', $user::class)
            ->where('user_id', (string) $user->getKey())
            ->latest('id')
            ->first();

        return Inertia::render('x-change/onboarding/MobileVerification', [
            'mobile' => $mobile === null
                ? 'Not configured'
                : substr($mobile, 0, 2).'••••••'.substr($mobile, -4),
            'verified' => $user->getAttribute('mobile_verified_at') !== null,
            'challenge' => $challenge === null ? null : [
                'status' => $challenge->status,
                'expires_at' => $challenge->expires_at?->toIso8601String(),
                'attempts' => $challenge->attempts,
            ],
            'local_code' => $this->localCode(),
            'status' => $request->session()->get('status'),
        ]);
    }

    private function localCode(): ?string
    {
        if (
            config('x-change.withdrawal.otp.driver', 'null') === 'null'
            && (bool) config('x-change.onboarding.mobile_verification.show_local_code', false)
            && app()->environment((array) config(
                'x-change.onboarding.mobile_verification.allow_null_driver_environments',
                ['local', 'testing'],
            ))
        ) {
            return '000000';
        }

        return null;
    }
}
