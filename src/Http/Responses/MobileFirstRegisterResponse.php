<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Responses;

use Laravel\Fortify\Contracts\RegisterResponse;
use Symfony\Component\HttpFoundation\Response;

final class MobileFirstRegisterResponse implements RegisterResponse
{
    public function toResponse($request): Response
    {
        if (data_get($request->user(), 'mobile_verified_at') === null) {
            return redirect()->route('x-change.onboarding.mobile-verification.show');
        }

        return redirect()->intended(config('fortify.home'));
    }
}
