<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Actions\Funding\ClaimPayCodeIntoAccount;
use LBHurtado\XChange\Http\Requests\Web\Cockpit\ClaimCockpitPayCodeFundingRequest;
use RuntimeException;
use Throwable;

final class CockpitPayCodeFundingClaimController extends Controller
{
    public function __invoke(
        ClaimCockpitPayCodeFundingRequest $request,
        ClaimPayCodeIntoAccount $claim,
    ): RedirectResponse {
        $claimant = $request->user();

        if (! $claimant instanceof Model) {
            throw new RuntimeException(
                'Account Funding requires a persisted Account owner.',
            );
        }

        try {
            $claim->handle(
                $request->validated('inspection_token'),
                $claimant,
            );
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('x-change.cockpit.funding.index')
                ->withErrors([
                    'pay_code_funding' => $exception instanceof RuntimeException
                        ? $exception->getMessage()
                        : 'The Pay Code could not be added to Client Funds.',
                ]);
        }

        return redirect()
            ->route('x-change.cockpit.funding.index')
            ->with(
                'funding_notice',
                'Pay Code added to Client Funds. No provider payout was made.',
            );
    }
}
