<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Contribution;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

final class ContributionPageController extends Controller
{
    public function __invoke(Request $request): Response
    {
        return Inertia::render('x-change/public/Contribution', [
            'contextUrl' => route('x-change.contributions.context'),
            'passwordUrl' => route('x-change.contributions.password.verify'),
            'uploadUrl' => route('x-change.contributions.documents.store'),
        ])->toResponse($request)->header('Cache-Control', 'no-store, private')->header('Referrer-Policy', 'no-referrer');
    }
}
