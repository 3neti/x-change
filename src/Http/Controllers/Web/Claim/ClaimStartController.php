<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Claim;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Response;
use LBHurtado\FormFlowManager\Data\FormFlowInstructionsData;
use LBHurtado\FormFlowManager\Services\DriverService;
use LBHurtado\FormFlowManager\Services\FormFlowService;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Claim\PrepareCompiledClaim;
use LBHurtado\XChange\Actions\Claim\PrepareCompiledClaimSubmission;
use LBHurtado\XChange\Actions\Claim\ResolveClaimExperience;
use LBHurtado\XChange\Actions\Claim\StorePreparedCompiledClaim;
use LBHurtado\XChange\Actions\Claim\SubmitCompiledClaimCompletion;
use LBHurtado\XChange\Http\Responses\ClaimEntryResponseFactory;
use LBHurtado\XChange\Support\Claim\ClaimExperiencePayload;

class ClaimStartController extends Controller
{
    public function __construct(
        protected DriverService $driverService,
        protected FormFlowService $formFlowService,
    ) {}

    public function __invoke(Request $request): RedirectResponse|Response
    {
        if ($request->isMethod('post') && $request->input('mode') === 'compiled_form') {
            $validated = $request->validate([
                'mode' => ['required', 'in:compiled_form'],
                'code' => ['required', 'string'],
                'inputs' => ['required', 'array'],
            ]);

            app(PrepareCompiledClaimSubmission::class)->handle($validated);

            $prepared = app(PrepareCompiledClaim::class)->handle();

            if (! $prepared->isValid()) {
                return back()->withErrors([
                    'code' => $prepared->errorMessage ?? 'Unable to prepare compiled claim.',
                ]);
            }

            app(StorePreparedCompiledClaim::class)->handle($prepared);

            $completionPayload = app(SubmitCompiledClaimCompletion::class)->handle();

            if ($completionPayload === null) {
                return back()->withErrors([
                    'code' => 'Unable to submit compiled claim.',
                ]);
            }

            return redirect()->route('x-change.claim.success', [
                'code' => $prepared->submission?->code,
            ]);
        }

        $code = strtoupper(trim((string) $request->query('code', '')));

        if ($code === '') {
            return $this->claimEntryResponse()->render(
                initialCode: null,
                claimExperience: null,
            );
        }

        if ($request->boolean('failed')) {
            return $this->claimEntryResponse()->render(
                initialCode: $code,
                claimExperience: null,
            );
        }

        $voucher = Voucher::query()->where('code', $code)->first();

        if (! $voucher) {
            return $this->claimEntryResponse()->error(
                message: 'Invalid Pay Code.',
                code: $code,
            );
        }

        if ($voucher->redeemed_at !== null) {
            return $this->claimEntryResponse()->error(
                message: 'This Pay Code has already been redeemed.',
                code: $code,
            );
        }

        if ($voucher->isExpired()) {
            return $this->claimEntryResponse()->error(
                message: 'This Pay Code has expired.',
                code: $code,
            );
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

    private function claimEntryResponse(): ClaimEntryResponseFactory
    {
        return app(ClaimEntryResponseFactory::class);
    }
}
