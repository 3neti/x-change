<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use LBHurtado\XChange\Actions\Funding\ApproveFundingReconciliation;
use LBHurtado\XChange\Http\Requests\Web\Cockpit\ApproveCockpitFundingReconciliationRequest;
use LBHurtado\XChange\Models\FundingReconciliationRequest;

class CockpitFundingReconciliationApprovalController extends Controller
{
    public function __invoke(
        ApproveCockpitFundingReconciliationRequest $request,
        FundingReconciliationRequest $reconciliationRequest,
        ApproveFundingReconciliation $approveReconciliation,
    ): RedirectResponse {
        $actor = $request->user();

        try {
            $approveReconciliation->handle(
                request: $reconciliationRequest,
                actorType: $actor::class,
                actorId: (string) $actor->getAuthIdentifier(),
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'approval' => $exception->getMessage(),
            ]);
        }

        return redirect()
            ->route('x-change.cockpit.funding.index')
            ->with('funding_notice', 'Reconciliation approved and executed.');
    }
}
