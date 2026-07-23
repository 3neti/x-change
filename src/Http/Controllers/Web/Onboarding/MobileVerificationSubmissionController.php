<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Onboarding;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Actions\Auth\VerifyMobileVerification;
use LBHurtado\XChange\Http\Requests\Web\Onboarding\VerifyMobileRequest;

final class MobileVerificationSubmissionController extends Controller
{
    /**
     * @throws AuthenticationException
     */
    public function __invoke(
        VerifyMobileRequest $request,
        VerifyMobileVerification $verify,
    ): RedirectResponse {
        $user = $request->user();

        if (! $user instanceof Model) {
            throw new AuthenticationException;
        }

        $verify->handle($user, $request->string('code')->toString());

        return redirect()
            ->route('x-change.cockpit.funding.index')
            ->with('funding_notice', 'Mobile verified. QR Ph funding simulation is ready.');
    }
}
