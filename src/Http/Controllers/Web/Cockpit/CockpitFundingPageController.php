<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\XChange\Services\Cockpit\FundingCockpitReadModelProvider;
use LBHurtado\XChange\Services\Funding\Base64PngQrPhFundingSimulationQrRenderer;
use LBHurtado\XChange\Support\Cockpit\CockpitReadOnlyPageProps;

class CockpitFundingPageController extends Controller
{
    public function __construct(
        private readonly CockpitReadOnlyPageProps $props,
        private readonly FundingCockpitReadModelProvider $funding,
        private readonly Base64PngQrPhFundingSimulationQrRenderer $simulationQr,
    ) {}

    /**
     * @throws AuthenticationException
     */
    public function __invoke(Request $request): Response
    {
        $operator = $request->user();

        if ($operator === null) {
            throw new AuthenticationException;
        }

        return Inertia::render('x-change/cockpit/Funding', [
            ...$this->props->toArray(),
            'funding_read_model' => $this->funding->forOperator($operator)->toArray(),
            'funding_instruction' => $request->session()->pull('funding_instruction'),
            'funding_notice' => $request->session()->pull('funding_notice'),
            'funding_simulation' => [
                'enabled' => (bool) config('x-change.cockpit.qrph_funding_simulation.enabled', false),
                'mode' => 'rollback-only',
                'provider_calls' => false,
                'balance_changes' => false,
                'amount' => '₱25.00',
                'mobile_ready' => data_get($operator, 'mobile_verified_at') !== null,
                'qr_code' => $this->simulationQr->render(2_500, 'PHP'),
            ],
        ]);
    }
}
