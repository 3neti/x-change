<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Legal;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

final class DeclineAgreementController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        Auth::guard()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->to((string) config('x-change.legal.eula.declined_redirect', '/'));
    }
}
