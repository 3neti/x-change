<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Onboarding;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use LBHurtado\XChange\Support\Claim\ClaimAuthenticationIntent;

final class OnboardingVoucherClaimantAuthenticator
{
    public function authenticate(Authenticatable $claimant, Request $request): bool
    {
        if (! $request->hasSession()) {
            return false;
        }

        Auth::guard()->login($claimant);
        $request->session()->regenerate();
        $request->session()->forget(ClaimAuthenticationIntent::SessionKey);
        $request->session()->forget('url.intended');

        return true;
    }
}
