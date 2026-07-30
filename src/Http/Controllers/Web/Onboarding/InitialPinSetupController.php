<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Onboarding;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\XChange\Actions\Auth\CompleteInitialPinSetup;
use LBHurtado\XChange\Http\Requests\Web\Onboarding\SetInitialPinRequest;
use LBHurtado\XChange\Services\Onboarding\AccountPinSetupState;

final class InitialPinSetupController extends Controller
{
    /**
     * @throws AuthenticationException
     */
    public function show(
        Request $request,
        AccountPinSetupState $pinSetup,
    ): Response|RedirectResponse {
        $user = $request->user();

        if (! $user instanceof Model) {
            throw new AuthenticationException;
        }

        if (! $pinSetup->isRequired($user)) {
            return redirect()->route('x-change.cockpit.dashboard');
        }

        return Inertia::render('x-change/onboarding/InitialPinSetup', [
            'mobile' => $this->maskedMobile($user),
        ]);
    }

    public function store(
        SetInitialPinRequest $request,
        CompleteInitialPinSetup $complete,
    ): RedirectResponse {
        /** @var Model $user */
        $user = $request->user();
        $validated = $request->validated();

        $complete->handle($user, (string) $validated['password']);
        $request->session()->put('auth.password_confirmed_at', time());
        $request->session()->regenerate();

        return redirect()
            ->route('x-change.cockpit.dashboard')
            ->with('status', 'Your PIN is ready. Welcome to x-change.');
    }

    private function maskedMobile(Model $user): string
    {
        $mobile = preg_replace(
            '/\D+/',
            '',
            (string) $user->getAttribute('mobile'),
        );

        if (! is_string($mobile) || strlen($mobile) < 6) {
            return 'Your verified mobile';
        }

        return substr($mobile, 0, 2).'••••••'.substr($mobile, -4);
    }
}
