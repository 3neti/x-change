<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Execution;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use LBHurtado\Onboarding\Actions\PromoteContactToUser;
use LBHurtado\Voucher\Contracts\ExecutionDriverContract;
use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Data\ExecutionResultData;
use LBHurtado\Voucher\Services\DefaultExecutionDriver;
use LBHurtado\XChange\Exceptions\OnboardingVoucherExecutionFailed;
use LBHurtado\XChange\Services\Onboarding\OnboardingVoucherClaimantAuthenticator;
use LBHurtado\XChange\Services\OnboardingVoucherInstructionPolicy;
use Throwable;

final readonly class OnboardingAccountProvisioningExecutionDriver implements ExecutionDriverContract
{
    public function __construct(
        private PromoteContactToUser $promoteContact,
        private DefaultExecutionDriver $defaultDriver,
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
        $verifiedAt = $this->verifiedAt($inputs);

        if ($verificationRequired && $verifiedAt === null) {
            return ExecutionResultData::failed(
                driver: $this->key(),
                failure: 'mobile_verification_required',
            );
        }

        try {
            return DB::transaction(fn (): ExecutionResultData => $this->executeAtomically(
                $context,
                $inputs,
                $verifiedAt,
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
     */
    private function executeAtomically(
        ExecutionContextData $context,
        array $inputs,
        ?string $verifiedAt,
    ): ExecutionResultData {
        $promotion = $this->promoteContact->handle($context->contact, [
            'name' => data_get($inputs, 'full_name', data_get($inputs, 'name')),
            'email' => data_get($inputs, 'email'),
            'mobile' => data_get($inputs, 'mobile', $context->contact->mobile),
            'mobile_verified' => $verifiedAt !== null,
        ]);

        if (! $promotion->promoted || ! $promotion->user instanceof Authenticatable) {
            throw new OnboardingVoucherExecutionFailed('account_provisioning_rejected');
        }

        $redemption = $this->defaultDriver->execute(
            $this->withoutSensitiveAuthenticationEvidence($context),
        );

        if (! $redemption->successful) {
            throw new OnboardingVoucherExecutionFailed(
                $redemption->failure ?? 'voucher_redemption_rejected',
            );
        }

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
                'onboarding.voucher_redeemed',
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
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $inputs
     */
    private function verifiedAt(array $inputs): ?string
    {
        foreach ([
            data_get($inputs, 'otp_verification.verified_at'),
            data_get($inputs, 'verified_at'),
        ] as $value) {
            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return null;
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

        if ($this->verifiedAt($inputs) !== null) {
            data_set($inputs, 'otp.value', 'verified');
            data_set($inputs, 'otp.verified', true);
        }

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
