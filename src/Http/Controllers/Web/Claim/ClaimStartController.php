<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Claim;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\FormFlowManager\Services\DriverService;
use LBHurtado\FormFlowManager\Services\FormFlowService;
use LBHurtado\Voucher\Models\Voucher;

class ClaimStartController extends Controller
{
    public function __construct(
        protected DriverService $driverService,
        protected FormFlowService $formFlowService,
    ) {}

    public function __invoke(Request $request): RedirectResponse|Response
    {
        $code = strtoupper(trim((string) $request->query('code', '')));

        if ($code === '') {
            return Inertia::render('x-change/claim/Entry', [
                'initial_code' => null,
            ]);
        }

        if ($request->boolean('failed')) {
            return Inertia::render('x-change/claim/Entry', [
                'initial_code' => $code,
            ]);
        }

        $voucher = Voucher::query()->where('code', $code)->first();

        if (! $voucher) {
            return Inertia::render('x-change/claim/Error', [
                'message' => 'Invalid Pay Code.',
                'code' => $code,
            ]);
        }

        if ($voucher->redeemed_at !== null) {
            return Inertia::render('x-change/claim/Error', [
                'message' => 'This Pay Code has already been redeemed.',
                'code' => $code,
            ]);
        }

        if ($voucher->isExpired()) {
            return Inertia::render('x-change/claim/Error', [
                'message' => 'This Pay Code has expired.',
                'code' => $code,
            ]);
        }

        // Transform voucher to form-flow instructions via YAML driver
        $instructions = $this->driverService->transform($voucher);

        // Start form-flow session
        $state = $this->formFlowService->startFlow($instructions);

        return redirect("/form-flow/{$state['flow_id']}");
    }
}
