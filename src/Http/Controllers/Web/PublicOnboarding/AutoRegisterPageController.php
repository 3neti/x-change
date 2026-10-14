<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\PublicOnboarding;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\XChange\Services\PublicAutoRegistrationService;

final class AutoRegisterPageController extends Controller
{
    public function __invoke(Request $request): Response
    {
        return Inertia::render('x-change/public/AutoRegister', [
            'amount_minor' => (int) config('x-change.public_auto_register.amount_minor', 10000),
            'currency' => strtoupper((string) config('x-change.public_auto_register.currency', 'PHP')),
            'turnstile' => [
                'enabled' => (bool) config('x-change.public_auto_register.turnstile.enabled', config('x-change.public_auto_generate.turnstile.enabled', false)),
                'site_key' => config('x-change.public_auto_register.turnstile.site_key', config('x-change.public_auto_generate.turnstile.site_key')),
            ],
            'endpoints' => [
                'start' => route('x-change.public-auto-register.start', [], false),
                'status' => route('x-change.public-auto-register.status', ['registration' => '__REFERENCE__'], false),
                'complete' => route('x-change.public-auto-register.complete', ['registration' => '__REFERENCE__'], false),
            ],
        ])->rootView('x-change::claim-root');
    }
}
