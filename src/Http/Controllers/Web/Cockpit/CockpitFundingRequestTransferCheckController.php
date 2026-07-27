<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Actions\Funding\CheckFundingRequestTransfer;
use LBHurtado\XChange\Models\FundingRequest;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CockpitFundingRequestTransferCheckController extends Controller
{
    public function __invoke(
        Request $request,
        FundingRequest $fundingRequest,
        CheckFundingRequestTransfer $check,
    ): RedirectResponse {
        $operator = $request->user();

        if ($operator === null
            || $fundingRequest->requester_type !== $operator::class
            || $fundingRequest->requester_id !== (string) $operator->getAuthIdentifier()) {
            throw new HttpException(404);
        }

        $result = $check->handle($fundingRequest);

        return redirect()
            ->route('x-change.cockpit.funding.index', ['mode' => 'bank_transfer'])
            ->with('funding_notice', $result->message);
    }
}
