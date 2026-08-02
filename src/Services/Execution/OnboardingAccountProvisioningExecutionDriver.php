<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Execution;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LBHurtado\Onboarding\Actions\PromoteContactToUser;
use LBHurtado\Voucher\Contracts\ExecutionDriverContract;
use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Data\ExecutionResultData;
use LBHurtado\Voucher\Services\DefaultExecutionDriver;
use LBHurtado\XChange\Actions\Claim\DispatchVoucherClaimOutcome;
use LBHurtado\XChange\Exceptions\OnboardingVoucherExecutionFailed;
use LBHurtado\XChange\Models\VoucherClaim;
use LBHurtado\XChange\Services\Onboarding\OnboardingVoucherClaimantAuthenticator;
use LBHurtado\XChange\Services\OnboardingVoucherInstructionPolicy;
use LBHurtado\XChange\Support\Auth\MobileNumber;
use Throwable;

final readonly class OnboardingAccountProvisioningExecutionDriver implements ExecutionDriverContract
{
    public function __construct(
        private PromoteContactToUser $promoteContact,
        private DefaultExecutionDriver $defaultDriver,
        private DispatchVoucherClaimOutcome $claimOutcomes,
        private OnboardingVoucherClaimantAuthenticator $authenticator,
        private Request $request,
    ) {}

    public function key(): string
    {
        return OnboardingVoucherInstructionPolicy::ExecutionDriver;
    }

    public function execute(ExecutionContextData $context): ExecutionResultData
    {
        if ($context->voucher === null) {
            return ExecutionResultData::failed(
                driver: $this->key(),
                failure: 'missing_voucher',
            );
        }

        $inputs = (array) data_get($context->meta, 'inputs', []);
        $verificationRequired = (bool) data_get(
            $context->instruction?->metadata,
            'onboarding.mobile_verification_required',
            true,
        );
        $verificationProof = $this->verificationProof($inputs);

        if ($verificationRequired && $verificationProof === null) {
            return ExecutionResultData::failed(
                driver: $this->key(),
                failure: 'mobile_verification_required',
            );
        }

        try {
            return DB::transaction(fn (): ExecutionResultData => $this->executeAtomically(
                $context,
                $inputs,
                $verificationProof,
            ));
        } catch (OnboardingVoucherExecutionFailed $exception) {
            return ExecutionResultData::failed(
                driver: $this->key(),
                failure: $exception->failure,
            );
        } catch (Throwable $exception) {
            return ExecutionResultData::failed(
                driver: $this->key(),
                failure: 'account_provisioning_failed',
                metadata: [
                    'voucher_code' => $context->voucherCode,
                    'exception' => $exception::class,
                ],
            );
        }
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @param  array{reference:string,purpose:string,verified_at:string}|null  $verificationProof
     */
    private function executeAtomically(
        ExecutionContextData $context,
        array $inputs,
        ?array $verificationProof,
    ): ExecutionResultData {
        $promotion = $this->promoteContact->handle($context->contact, [
            'name' => data_get($inputs, 'full_name', data_get($inputs, 'name')),
            'email' => data_get($inputs, 'email'),
            'mobile' => data_get($inputs, 'mobile', $context->contact->mobile),
            'mobile_verified' => $verificationProof !== null,
        ]);

        if (! $promotion->promoted || ! $promotion->user instanceof Authenticatable) {
            throw new OnboardingVoucherExecutionFailed('account_provisioning_rejected');
        }

        $settlement = $this->settle($context, $promotion->user);

        $handoffScheduled = $this->request->hasSession();

        if ($handoffScheduled) {
            DB::afterCommit(fn (): bool => $this->authenticator->authenticate(
                $promotion->user,
                $this->request,
            ));
        }

        return new ExecutionResultData(
            execution_id: null,
            successful: true,
            status: 'succeeded',
            driver: $this->key(),
            events: [
                'onboarding.account_resolved',
                'onboarding.account_positions_provisioned',
                $settlement['event'],
                $handoffScheduled
                    ? 'onboarding.claimant_authentication_scheduled'
                    : 'onboarding.claimant_handoff_deferred',
            ],
            metadata: [
                'voucher_code' => $context->voucherCode,
                'account_model' => $promotion->user::class,
                'account_key' => (string) $promotion->user->getAuthIdentifier(),
                'account_reused' => (bool) data_get($promotion->meta, 'reused', false),
                'principal_reference' => data_get($promotion->meta, 'principal_reference'),
                'position_count' => (int) data_get($promotion->meta, 'position_count', 0),
                'claimant_authentication_scheduled' => $handoffScheduled,
                'identity_verification_reference_hash' => $verificationProof !== null
                    ? hash('sha256', $verificationProof['reference'])
                    : null,
                'settlement_mode' => $settlement['mode'],
                'treasury_operation_reference' => $settlement['treasury_operation_reference'],
            ],
        );
    }

    /**
     * @return array{event:string,mode:string,treasury_operation_reference:?string}
     */
    private function settle(
        ExecutionContextData $context,
        Authenticatable $claimant,
    ): array {
        $defaultOutcome = data_get(
            $context->voucher?->metadata,
            'instructions.claim.default_outcome',
        );

        if ($defaultOutcome === 'account_funding') {
            $claim = $this->claimOutcomes->handle(
                voucher: $context->voucher,
                requestedOutcome: 'account_funding',
                payload: [],
                claimant: $claimant,
            );

            if (! $claim instanceof VoucherClaim || $claim->status !== 'succeeded') {
                throw new OnboardingVoucherExecutionFailed(
                    'account_funding_rejected',
                );
            }

            return [
                'event' => 'onboarding.account_funded',
                'mode' => 'account_funding',
                'treasury_operation_reference' => $claim->treasury_operation_reference,
            ];
        }

        $redemption = $this->defaultDriver->execute(
            $this->withoutSensitiveAuthenticationEvidence($context),
        );

        if (! $redemption->successful) {
            throw new OnboardingVoucherExecutionFailed(
                $redemption->failure ?? 'voucher_redemption_rejected',
            );
        }

        return [
            'event' => 'onboarding.voucher_redeemed',
            'mode' => 'voucher_redemption',
            'treasury_operation_reference' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $inputs
     */
    private function verificationProof(array $inputs): ?array
    {
        $reference = data_get($inputs, 'otp.verification_reference');
        $purpose = data_get($inputs, 'otp.verification_purpose');
        $verifiedAt = data_get($inputs, 'otp.verified_at');
        $proofMobileValue = data_get($inputs, 'otp.mobile');
        $claimMobileValue = data_get($inputs, 'mobile');
        $proofMobile = MobileNumber::normalize(
            is_string($proofMobileValue) ? $proofMobileValue : null,
        );
        $claimMobile = MobileNumber::normalize(
            is_string($claimMobileValue) ? $claimMobileValue : null,
        );
        $expectedPurpose = (string) config(
            'x-change.onboarding.identity_otp.purpose',
            'onboarding.account',
        );

        if (! is_string($reference) || trim($reference) === ''
            || ! is_string($purpose) || ! hash_equals($expectedPurpose, $purpose)
            || ! is_string($verifiedAt) || trim($verifiedAt) === ''
            || $proofMobile === null || $claimMobile === null
            || ! hash_equals($claimMobile, $proofMobile)) {
            return null;
        }

        try {
            $verified = Carbon::parse($verifiedAt);
        } catch (Throwable) {
            return null;
        }

        $ttlMinutes = max(1, (int) config(
            'x-change.onboarding.identity_otp.proof_ttl_minutes',
            15,
        ));

        if ($verified->isFuture() || $verified->lessThan(now()->subMinutes($ttlMinutes))) {
            return null;
        }

        return [
            'reference' => trim($reference),
            'purpose' => $purpose,
            'verified_at' => $verified->toIso8601String(),
        ];
    }

    private function withoutSensitiveAuthenticationEvidence(
        ExecutionContextData $context,
    ): ExecutionContextData {
        $meta = $context->meta;
        $inputs = (array) data_get($meta, 'inputs', []);

        Arr::forget($inputs, [
            'otp_code',
            'otp.code',
            'otp.otp',
            'otp.otp_code',
            'otp_verification.otp',
            'otp_verification.otp_code',
        ]);

        if ($this->verificationProof($inputs) !== null) {
            data_set($inputs, 'otp.value', 'verified');
            data_set($inputs, 'otp.verified', true);
        }

        Arr::forget($inputs, [
            'verification_reference',
            'verification_purpose',
            'otp.verification_reference',
            'otp.verification_purpose',
        ]);

        data_set($meta, 'inputs', $inputs);

        return new ExecutionContextData(
            contact: $context->contact,
            voucherCode: $context->voucherCode,
            meta: $meta,
            voucher: $context->voucher,
            instruction: $context->instruction,
            correlation: $context->correlation,
        );
    }
}
