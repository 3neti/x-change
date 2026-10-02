<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\XChange\Services\PublicIssuance\PublicPricingPageReadModel;

final class PublicPricingPageController extends Controller
{
    public function __invoke(PublicPricingPageReadModel $pricing): Response
    {
        return Inertia::render('x-change/public/Pricing', [
            'pricing' => $pricing->present(),
            'public_navigation' => [
                'pricing_url' => route('x-change.pricing.show', [], false),
                'claim_url' => Route::has('x-change.claim.start')
                    ? route('x-change.claim.start', [], false)
                    : null,
                'create_url' => Route::has('x-change.public-auto-generate.show')
                    ? route('x-change.public-auto-generate.show', [], false)
                    : null,
                'login_url' => Route::has('login') ? route('login', [], false) : null,
                'register_url' => Route::has('register') ? route('register', [], false) : null,
            ],
        ])->rootView('x-change::claim-root');
    }
}
