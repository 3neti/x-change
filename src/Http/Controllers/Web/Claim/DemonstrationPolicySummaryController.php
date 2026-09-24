<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Claim;

use Illuminate\Http\Request;
use Inertia\Inertia;
use LBHurtado\XChange\Models\PolicyCompletionOutcome;
use LBHurtado\XChange\Services\Settlement\DemonstrationPolicySummary;
use Symfony\Component\HttpFoundation\Response;

final class DemonstrationPolicySummaryController
{
    public function __invoke(Request $request, PolicyCompletionOutcome $outcome, DemonstrationPolicySummary $summaries): Response
    {
        abort_unless($summaries->url($outcome) !== null, 404);
        Inertia::encryptHistory();
        $response = Inertia::render('x-change/claim/DemonstrationPolicySummary', [
            'summary' => $summaries->present($outcome),
            'applicant' => $summaries->privateApplicantDetails($outcome),
        ])->toResponse($request);
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
