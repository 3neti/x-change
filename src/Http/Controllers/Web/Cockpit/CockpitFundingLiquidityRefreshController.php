<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Actions\Funding\RefreshFundingLiquidity;

final class CockpitFundingLiquidityRefreshController extends Controller
{
    /**
     * @throws AuthenticationException
     */
    public function __invoke(
        Request $request,
        RefreshFundingLiquidity $refresh,
    ): RedirectResponse {
        $operator = $request->user();

        if (! $operator instanceof Authenticatable) {
            throw new AuthenticationException;
        }

        $result = $refresh->handle($operator);

        if (! $result->succeeded()) {
            return redirect()
                ->route('x-change.cockpit.funding.index')
                ->withErrors([
                    'liquidity_refresh' => $result->busy > 0
                        ? 'Liquidity is already being refreshed. Try again shortly.'
                        : 'Provider liquidity could not be refreshed. The last observation was retained.',
                ]);
        }

        return redirect()
            ->route('x-change.cockpit.funding.index')
            ->with(
                'funding_notice',
                $result->hasIncompleteConnections()
                    ? 'Available provider liquidity was refreshed. Some connections remain unavailable.'
                    : 'Provider liquidity refreshed. Issuance Capacity was recalculated.',
            );
    }
}
