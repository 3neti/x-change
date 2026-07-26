<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Actions\Funding\ApproveFundingRequestAndIssueCode;
use LBHurtado\XChange\Http\Requests\Web\Cockpit\ApproveCockpitFundingRequestRequest;
use LBHurtado\XChange\Jobs\Funding\PayApprovedFundingRequestJob;
use LBHurtado\XChange\Models\FundingRequest;

class CockpitFundingRequestApprovalController extends Controller
{
    public function __invoke(
        ApproveCockpitFundingRequestRequest $request,
        FundingRequest $fundingRequest,
        ApproveFundingRequestAndIssueCode $approve,
    ): RedirectResponse {
        $actor = $request->user();
        $approve->handle(
            $fundingRequest,
            $actor::class,
            (string) $actor->getAuthIdentifier(),
        );
        PayApprovedFundingRequestJob::dispatch($fundingRequest->reference)
            ->afterCommit();

        return redirect()
            ->route('x-change.cockpit.funding.index')
            ->with(
                'funding_notice',
                'Account Funding accepted. System Treasury payment was queued.',
            );
    }
}
