<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use LBHurtado\XChange\Actions\Funding\ApproveFundingReconciliation;
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
        ApproveFundingReconciliation $approveReconciliation,
    ): RedirectResponse {
        $actor = $request->user();
        $action = FundingReconciliationAction::from((string) $request->validated('action'));

        try {
            DB::transaction(function () use ($requestReconciliation, $approveReconciliation, $case, $action, $actor): void {
                $reconciliationRequest = $requestReconciliation->handle(
                    case: $case,
                    action: $action,
                    actorType: $actor::class,
                    actorId: (string) $actor->getAuthIdentifier(),
                );

                if ($action === FundingReconciliationAction::RecoverPaidOnDemandIssuance) {
                    $approveReconciliation->handle(
                        request: $reconciliationRequest,
                        actorType: $actor::class,
                        actorId: (string) $actor->getAuthIdentifier(),
                    );
                }
            });
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'reconciliation' => $exception->getMessage(),
            ]);
        }

        return redirect()
            ->route('x-change.cockpit.funding.index')
            ->with('funding_notice', $action === FundingReconciliationAction::RecoverPaidOnDemandIssuance
                ? 'Payment recovered. Pay Code issuance is underway.'
                : 'Reconciliation request submitted for independent approval.');
    }
}
