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
use LBHurtado\XChange\Contracts\ClaimWorkflowResolverContract;
use LBHurtado\XChange\Contracts\VoucherFlowCapabilityResolverContract;
use LBHurtado\XChange\Data\PreparedCompiledClaimData;
use LBHurtado\XChange\Enums\ClaimAuthenticationMode;
use LBHurtado\XChange\Http\Responses\ClaimEntryResponseFactory;
use LBHurtado\XChange\Services\BuildProvisioningRequirementViewData;
use LBHurtado\XChange\Services\Claim\FormFlowClaimWorkflowMutator;
use LBHurtado\XChange\Services\Claim\VoucherClaimFlowCompiler;
use LBHurtado\XChange\Services\NamedVoucherSliceService;
use LBHurtado\XChange\Support\Claim\ClaimAuthenticationIntent;
use LBHurtado\XChange\Support\Claim\ClaimExperiencePayload;
use LBHurtado\XChange\Support\Claim\CompiledClaimResultRedirector;
use LBHurtado\XChange\Support\Claim\CompiledClaimResultSession;
use LBHurtado\XChange\Support\Claim\CompiledClaimSessionKeys;
use LBHurtado\XChange\Support\Claim\FormFlowSplashSkipPolicy;

class ClaimStartController extends Controller
{
    public function __construct(
        protected VoucherClaimFlowCompiler $claimFlowCompiler,
        protected DriverService $driverService,
        protected FormFlowService $formFlowService,
        protected BuildProvisioningRequirementViewData $provisioning,
        protected NamedVoucherSliceService $namedSlices,
        protected VoucherFlowCapabilityResolverContract $capabilities,
        protected ClaimWorkflowResolverContract $claimWorkflows,
        protected FormFlowClaimWorkflowMutator $formFlowWorkflows,
        protected ClaimAuthenticationIntent $claimAuthenticationIntent,
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

            if (
                $this->voucherRequiresSecret($prepared->voucher)
                && ! $this->claimSecretMatches($prepared->voucher, $preparedData->inputs['secret'] ?? null)
            ) {
                return back()->withErrors([
                    'secret' => 'That passcode does not match this Pay Code.',
                ]);
            }

            if (
                $this->namedSlices->hasNamedSlices($prepared->voucher)
                || $this->voucherRequiresSecret($prepared->voucher)
            ) {
                try {
                    return $this->startFormFlowForCompiledInputs(
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

        $code = strtoupper(trim((string) (
            $request->route('code')
            ?? $request->query('code', '')
        )));
        $onboardingReference = $this->normalizedOnboardingReference(
            $request->input('onboarding_reference', $request->query('onboarding_reference'))
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

        if (! $this->capabilities->resolve($voucher)->can_disburse) {
            return $this->claimEntryResponse()->error(
                message: 'This Pay Code accepts payment and cannot be claimed.',
                code: $code,
            );
        }

        $workflow = $this->claimWorkflows->resolve($voucher);
        $authenticatedMobile = null;

        if ($workflow->authentication_mode === ClaimAuthenticationMode::AuthenticatedOfficer) {
            $officer = $request->user();

            if ($officer === null) {
                $this->claimAuthenticationIntent->remember($request, $code, $workflow);

                return redirect()->route('x-change.claim.authorization-required', ['code' => $code]);
            }

            $authenticatedMobile = $officer->getAttribute('mobile');

            if (! is_string($authenticatedMobile) || trim($authenticatedMobile) === '') {
                return back()->withErrors([
                    'code' => 'Your authenticated officer profile needs a verified mobile number before it can authorize a campaign.',
                ]);
            }
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

        $compiledFlow = $this->claimFlowCompiler->compile($voucher, $authenticatedMobile);
        $claimExperience = ResolveClaimExperience::run($voucher)->toArray();

        if ($this->claimExperienceUsesClaimWidgetFormFlow($claimExperience)) {
            return $this->claimEntryResponse()->render(
                initialCode: $code,
                claimExperience: $claimExperience,
                provisioningRequirement: null,
            );
        }

        $instructionPayload = $compiledFlow->instructions->toArray();

        if ($onboardingReference !== null) {
            data_set($instructionPayload, 'metadata.onboarding_reference', $onboardingReference);
        }

        $instructions = FormFlowInstructionsData::from($instructionPayload);

        $state = $this->formFlowService->startFlow($instructions);

        return redirect("/form-flow/{$state['flow_id']}");
    }

    /**
     * @param  array<string, mixed>  $claimExperience
     * @param  array<string, mixed>  $inputs
     */
    protected function startFormFlowForCompiledInputs(Voucher $voucher, array $claimExperience, array $inputs): RedirectResponse
    {
        $sliceIds = $inputs['slice_ids'] ?? [];
        $amount = null;

        if ($this->namedSlices->hasNamedSlices($voucher)) {
            $payload = $this->namedSlices->enrichClaimPayload($voucher, [
                'slice_ids' => $sliceIds,
            ]);

            $amount = (float) data_get($payload, 'amount', 0);
            $sliceIds = data_get($payload, 'slice_ids', []);
        }

        $instructions = $this->driverService->transform($voucher);
        $instructionPayload = ClaimExperiencePayload::putIntoInstructions(
            $instructions->toArray(),
            $claimExperience,
        );

        $instructionPayload = $this->applyNamedSliceDefaults(
            instructionPayload: $instructionPayload,
            amount: $amount,
            sliceIds: $sliceIds,
            compiledInputs: $inputs,
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
    protected function applyNamedSliceDefaults(array $instructionPayload, ?float $amount, array $sliceIds, array $compiledInputs = []): array
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

                if ($amount !== null && ($field['name'] ?? null) === 'amount') {
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

            if ($sliceIds !== [] && ! $hasSliceIdsField) {
                $fields[] = [
                    'name' => 'slice_ids',
                    'type' => 'hidden',
                    'default' => implode(',', $sliceIds),
                    'required' => false,
                ];
            }

            if (
                array_key_exists('secret', $compiledInputs)
                && is_string($compiledInputs['secret'])
                && trim($compiledInputs['secret']) !== ''
            ) {
                $fields[] = [
                    'name' => 'secret',
                    'type' => 'hidden',
                    'default' => trim($compiledInputs['secret']),
                    'required' => false,
                ];
            }

            data_set($instructionPayload, "steps.{$stepIndex}.config.fields", $fields);

            break;
        }

        if ($sliceIds !== []) {
            data_set($instructionPayload, 'metadata.named_slices.selected_ids', $sliceIds);
        }

        if ($amount !== null) {
            data_set($instructionPayload, 'metadata.named_slices.amount', $amount);
        }

        return $instructionPayload;
    }

    protected function voucherRequiresSecret(Voucher $voucher): bool
    {
        return filled(data_get($voucher->metadata ?? [], 'instructions.cash.validation.secret'));
    }

    protected function claimSecretMatches(Voucher $voucher, mixed $secret): bool
    {
        $expected = data_get($voucher->metadata ?? [], 'instructions.cash.validation.secret');

        if (! is_string($expected) || trim($expected) === '') {
            return true;
        }

        if (! is_string($secret)) {
            return false;
        }

        return hash_equals($expected, trim($secret));
    }

    /**
     * @param  array<string, mixed>  $claimExperience
     */
    protected function claimExperienceUsesClaimWidgetFormFlow(array $claimExperience): bool
    {
        $formFlow = collect((array) data_get($claimExperience, 'phases', []))
            ->firstWhere('key', 'form_flow');
        $fields = is_array($formFlow) ? data_get($formFlow, 'fields') : null;

        return is_array($formFlow)
            && ($formFlow['owner'] ?? null) === 'claim-widget'
            && is_array($fields)
            && $fields !== [];
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
