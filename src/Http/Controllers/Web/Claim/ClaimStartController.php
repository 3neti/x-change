<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Claim;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\FormFlowManager\Data\FormFlowInstructionsData;
use LBHurtado\FormFlowManager\Services\DriverService;
use LBHurtado\FormFlowManager\Services\FormFlowService;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Claim\ResolveClaimExperience;
use LBHurtado\XChange\Support\Claim\ClaimExperiencePayload;

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
                'claim_experience' => null,
            ]);
        }

        if ($request->boolean('failed')) {
            return Inertia::render('x-change/claim/Entry', [
                'initial_code' => $code,
                'claim_experience' => null,
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

        $claimExperience = ResolveClaimExperience::run($voucher)->toArray();

        $instructions = $this->driverService->transform($voucher);

        $instructionPayload = ClaimExperiencePayload::putIntoInstructions(
            $instructions->toArray(),
            $claimExperience,
        );

        $instructions = FormFlowInstructionsData::from($instructionPayload);

        $state = $this->formFlowService->startFlow($instructions);

        return redirect("/form-flow/{$state['flow_id']}");
    }
}
