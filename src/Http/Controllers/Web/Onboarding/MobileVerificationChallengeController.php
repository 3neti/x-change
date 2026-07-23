<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Onboarding;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Actions\Auth\StartMobileVerification;

final class MobileVerificationChallengeController extends Controller
{
    /**
     * @throws AuthenticationException
     */
    public function __invoke(
        Request $request,
        StartMobileVerification $start,
    ): RedirectResponse {
        $user = $request->user();

        if (! $user instanceof Model) {
            throw new AuthenticationException;
        }

        $start->handle($user);

        return back()->with('status', 'Verification code requested.');
    }
}
