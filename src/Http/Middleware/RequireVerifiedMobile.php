<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireVerifiedMobile
{
    public function handle(Request $request, Closure $next): Response
    {
        if (data_get($request->user(), 'mobile_verified_at') !== null) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Verify your mobile number before continuing.',
            ], 403);
        }

        return redirect()->guest(route('x-change.onboarding.mobile-verification.show'));
    }
}
