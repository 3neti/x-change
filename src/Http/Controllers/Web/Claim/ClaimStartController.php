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
use LBHurtado\XChange\Actions\Claim\SubmitCompiledFormClaim;
use LBHurtado\XChange\Data\PreparedCompiledClaimData;
use LBHurtado\XChange\Http\Responses\ClaimEntryResponseFactory;
use LBHurtado\XChange\Services\BuildProvisioningRequirementViewData;
use LBHurtado\XChange\Services\NamedVoucherSliceService;
use LBHurtado\XChange\Support\Claim\ClaimExperiencePayload;
use LBHurtado\XChange\Support\Claim\CompiledClaimResultRedirector;
use LBHurtado\XChange\Support\Claim\CompiledClaimResultSession;
use LBHurtado\XChange\Support\Claim\CompiledClaimSessionKeys;
use LBHurtado\XChange\Support\Claim\FormFlowSplashSkipPolicy;

class ClaimStartController extends Controller
{
    public function __construct(
        protected DriverService $driverService,
        protected FormFlowService $formFlowService,
        protected BuildProvisioningRequirementViewData $provisioning,
        protected NamedVoucherSliceService $namedSlices,
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

            $preparedPayload = app(StorePreparedCompiledClaim::class)->handle($prepared);
            $preparedData = PreparedCompiledClaimData::fromSessionPayload($preparedPayload);

            if (! $preparedData || ! $prepared->voucher) {
                return back()->withErrors([
                    'code' => 'Unable to submit compiled claim.',
                ]);
            }

            if ($this->namedSlices->hasNamedSlices($prepared->voucher)) {
                try {
                    return $this->startFormFlowForNamedSlices(
                        voucher: $prepared->voucher,
                        claimExperience: ResolveClaimExperience::run($prepared->voucher)->toArray(),
                        inputs: $preparedData->inputs,
                    );
                } catch (\Throwable $e) {
                    return back()->withErrors([
                        'slice_ids' => $this->claimErrorMessage($e),
                    ]);
                }
            }

            try {
                $claimResult = app(SubmitCompiledFormClaim::class)->handle(
                    voucher: $prepared->voucher,
                    prepared: $preparedData,
                );
            } catch (\Throwable $e) {
                return back()->withErrors([
                    'code' => $this->claimErrorMessage($e),
                ]);
            }

            app(CompiledClaimResultSession::class)->put($claimResult);

            session()->forget(CompiledClaimSessionKeys::SUBMISSION);
            session()->forget(CompiledClaimSessionKeys::PREPARED);

            return app(CompiledClaimResultRedirector::class)->redirect(
                voucher: $prepared->voucher,
                result: $claimResult,
            );
        }

        $code = strtoupper(trim((string) $request->query('code', '')));
        $onboardingReference = $this->normalizedOnboardingReference(
            $request->query('onboarding_reference')
        );

        if ($code === '') {
            return $this->claimEntryResponse()->render(
                initialCode: null,
                claimExperience: null,
                provisioningRequirement: null,
            );
        }

        if ($request->boolean('failed')) {
            return $this->claimEntryResponse()->render(
                initialCode: $code,
                claimExperience: null,
                provisioningRequirement: $this->provisioning->handle(
                    session()->get(CompiledClaimSessionKeys::PROVISIONING_REQUIREMENT)
                ),
            );
        }

        $voucher = Voucher::query()->where('code', $code)->first();

        if (! $voucher) {
            return $this->claimEntryResponse()->error(
                message: 'Invalid Pay Code.',
                code: $code,
            );
        }

        if ($this->namedSlices->hasNamedSlices($voucher)) {
            if ($this->namedSlices->allSlicesClaimed($voucher)) {
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

            if ($voucher->redeemed_at === null && ! $voucher->canRedeem()) {
                return $this->claimEntryResponse()->error(
                    message: 'This Pay Code cannot be redeemed.',
                    code: $code,
                );
            }

            $claimExperience = ResolveClaimExperience::run($voucher)->toArray();

            return $this->claimEntryResponse()->render(
                initialCode: $code,
                claimExperience: $claimExperience,
                provisioningRequirement: null,
            );
        }

        if ($voucher->redeemed_at !== null) {
            return $this->claimEntryResponse()->error(
                message: 'This Pay Code has already been redeemed.',
                code: $code,
            );
        }

        if ($this->isExhaustedDivisibleVoucher($voucher)) {
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

        if ($onboardingReference !== null) {
            data_set($instructionPayload, 'metadata.onboarding_reference', $onboardingReference);
        }

        $instructionPayload = app(FormFlowSplashSkipPolicy::class)->apply($instructionPayload);

        $instructions = FormFlowInstructionsData::from($instructionPayload);

        $state = $this->formFlowService->startFlow($instructions);

        return redirect("/form-flow/{$state['flow_id']}");
    }

    /**
     * @param  array<string, mixed>  $claimExperience
     * @param  array<string, mixed>  $inputs
     */
    protected function startFormFlowForNamedSlices(Voucher $voucher, array $claimExperience, array $inputs): RedirectResponse
    {
        $payload = $this->namedSlices->enrichClaimPayload($voucher, [
            'slice_ids' => $inputs['slice_ids'] ?? [],
        ]);

        $instructions = $this->driverService->transform($voucher);
        $instructionPayload = ClaimExperiencePayload::putIntoInstructions(
            $instructions->toArray(),
            $claimExperience,
        );

        $instructionPayload = $this->applyNamedSliceDefaults(
            instructionPayload: $instructionPayload,
            amount: (float) data_get($payload, 'amount', 0),
            sliceIds: data_get($payload, 'slice_ids', []),
        );

        $instructionPayload = app(FormFlowSplashSkipPolicy::class)->apply($instructionPayload);

        $state = $this->formFlowService->startFlow(
            FormFlowInstructionsData::from($instructionPayload),
        );

        session()->forget(CompiledClaimSessionKeys::SUBMISSION);
        session()->forget(CompiledClaimSessionKeys::PREPARED);

        return redirect("/form-flow/{$state['flow_id']}");
    }

    /**
     * @param  array<string, mixed>  $instructionPayload
     * @param  array<int, string>  $sliceIds
     * @return array<string, mixed>
     */
    protected function applyNamedSliceDefaults(array $instructionPayload, float $amount, array $sliceIds): array
    {
        foreach ((array) data_get($instructionPayload, 'steps', []) as $stepIndex => $step) {
            if (data_get($step, 'handler') !== 'form') {
                continue;
            }

            $fields = (array) data_get($step, 'config.fields', []);
            $hasSliceIdsField = false;

            foreach ($fields as $fieldIndex => $field) {
                if (! is_array($field)) {
                    continue;
                }

                if (($field['name'] ?? null) === 'amount') {
                    $fields[$fieldIndex]['default'] = $amount;
                    $fields[$fieldIndex]['readonly'] = true;
                    $fields[$fieldIndex]['slice_mode'] = null;
                    $fields[$fieldIndex]['available_balance'] = $amount;
                }

                if (($field['name'] ?? null) === 'slice_ids') {
                    $hasSliceIdsField = true;
                    $fields[$fieldIndex]['default'] = implode(',', $sliceIds);
                }
            }

            if (! $hasSliceIdsField) {
                $fields[] = [
                    'name' => 'slice_ids',
                    'type' => 'hidden',
                    'default' => implode(',', $sliceIds),
                    'required' => false,
                ];
            }

            data_set($instructionPayload, "steps.{$stepIndex}.config.fields", $fields);

            break;
        }

        data_set($instructionPayload, 'metadata.named_slices.selected_ids', $sliceIds);
        data_set($instructionPayload, 'metadata.named_slices.amount', $amount);

        return $instructionPayload;
    }

    private function claimEntryResponse(): ClaimEntryResponseFactory
    {
        return app(ClaimEntryResponseFactory::class);
    }

    protected function normalizedOnboardingReference(mixed $reference): ?string
    {
        if (! is_string($reference)) {
            return null;
        }

        $reference = trim($reference);

        return $reference === '' ? null : $reference;
    }

    protected function isExhaustedDivisibleVoucher(Voucher $voucher): bool
    {
        if (! method_exists($voucher, 'isDivisible') || ! $voucher->isDivisible()) {
            return false;
        }

        $remainingBalance = $this->safeVoucherCall($voucher, 'getRemainingBalance');

        if (is_numeric($remainingBalance) && (float) $remainingBalance <= 0.0) {
            return true;
        }

        $remainingSlices = $this->safeVoucherCall($voucher, 'getRemainingSlices');

        return is_numeric($remainingSlices) && (int) $remainingSlices <= 0;
    }

    protected function safeVoucherCall(Voucher $voucher, string $method): mixed
    {
        if (! method_exists($voucher, $method)) {
            return null;
        }

        try {
            return $voucher->{$method}();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function claimErrorMessage(\Throwable $e): string
    {
        return match ($e->getMessage()) {
            'Withdrawal amount is required for open-slice vouchers.' => 'Enter a withdrawal amount to continue with this Pay Code.',
            default => $e->getMessage(),
        };
    }
}
