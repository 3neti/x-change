<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use LBHurtado\XChange\Services\Onboarding\AccountPinSetupState;
use Symfony\Component\HttpFoundation\Response;

final readonly class RequireInitialPinSetup
{
    public function __construct(
        private AccountPinSetupState $pinSetup,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(
        Request $request,
        Closure $next,
    ): Response|RedirectResponse {
        $user = $request->user();

        if (
            ! $user instanceof Model
            || ! $this->pinSetup->isRequired($user)
            || $this->isAllowedRoute($request)
        ) {
            return $next($request);
        }

        return redirect()->route('x-change.onboarding.pin.show');
    }

    private function isAllowedRoute(Request $request): bool
    {
        return $request->routeIs(
            'x-change.onboarding.pin.*',
            'logout',
            'password.*',
        );
    }
}
