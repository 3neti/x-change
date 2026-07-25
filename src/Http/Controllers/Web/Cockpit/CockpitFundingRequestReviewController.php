<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Actions\Funding\PrepareFundingRequest;
use LBHurtado\XChange\Data\Funding\PrepareFundingRequestData;
use LBHurtado\XChange\Http\Requests\Web\Cockpit\PrepareCockpitFundingRequestRequest;
use LBHurtado\XChange\Models\FundingRequest;

class CockpitFundingRequestReviewController extends Controller
{
    public function __invoke(
        PrepareCockpitFundingRequestRequest $request,
        FundingRequest $fundingRequest,
        PrepareFundingRequest $prepare,
    ): RedirectResponse {
        $actor = $request->user();
        $validated = $request->validated();
        $prepare->handle($fundingRequest, new PrepareFundingRequestData(
            recognizedValueMinor: $validated['recognized_value_minor'],
            currency: $validated['currency'],
            connectionReference: $validated['connection_reference'],
            evidenceReference: $validated['evidence_reference'],
            reviewerType: $actor::class,
            reviewerId: (string) $actor->getAuthIdentifier(),
            reviewNotes: ($validated['review_notes'] ?? '') ?: null,
        ));

        return redirect()
            ->route('x-change.cockpit.funding.index')
            ->with('funding_notice', 'Backing review recorded. Independent approval is now required.');
    }
}
