<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use LBHurtado\XChange\Actions\Funding\RequestFundingReconciliation;
use LBHurtado\XChange\Enums\FundingReconciliationAction;
use LBHurtado\XChange\Http\Requests\Web\Cockpit\RequestCockpitFundingReconciliationRequest;
use LBHurtado\XChange\Models\FundingSuspenseCase;

class CockpitFundingReconciliationRequestController extends Controller
{
    public function __invoke(
        RequestCockpitFundingReconciliationRequest $request,
        FundingSuspenseCase $case,
        RequestFundingReconciliation $requestReconciliation,
    ): RedirectResponse {
        $actor = $request->user();
        $action = FundingReconciliationAction::from((string) $request->validated('action'));

        try {
            $requestReconciliation->handle(
                case: $case,
                action: $action,
                actorType: $actor::class,
                actorId: (string) $actor->getAuthIdentifier(),
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'reconciliation' => $exception->getMessage(),
            ]);
        }

        return redirect()
            ->route('x-change.cockpit.funding.index')
            ->with('funding_notice', 'Reconciliation request submitted for independent approval.');
    }
}
