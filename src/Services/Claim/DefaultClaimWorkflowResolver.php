<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Claim;

use Illuminate\Validation\ValidationException;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\ClaimWorkflowResolverContract;
use LBHurtado\XChange\Data\Claim\ClaimWorkflowDescriptorData;
use LBHurtado\XChange\Enums\ClaimAuthenticationMode;
use LBHurtado\XChange\Services\Execution\CampaignCoverageCompletionExecutionDriver;
use LBHurtado\XChange\Services\OnboardingVoucherInstructionPolicy;
use LBHurtado\XChange\Services\Settlement\CampaignWalletPayerMobile;

final class DefaultClaimWorkflowResolver implements ClaimWorkflowResolverContract
{
    public function resolve(Voucher $voucher): ClaimWorkflowDescriptorData
    {
        $this->assertSupportedIntent($voucher);
        $driver = $this->executionDriver($voucher);

        if ($driver === CampaignCoverageCompletionExecutionDriver::Key) {
            return new ClaimWorkflowDescriptorData(
                key: 'campaign.coverage-completion.v1',
                requires_mobile: true,
                requires_destination: false,
                requires_amount: false,
                title: 'Complete Your Details',
                description: 'Your payment has been received. Complete the required personal details to continue.',
                confirmation_label: 'Submit Details',
                confirmation_title: 'Review your details',
                authentication_mode: ClaimAuthenticationMode::ClaimantHandoff,
                required_claim_fields: ['mobile'],
                review: [
                    'coverage_completion' => true,
                    'bound_mobile' => (new CampaignWalletPayerMobile)->forCompletionVoucher($voucher),
                    'completion_destination' => 'claim_success',
                ],
            );
        }

        if ($this->isCampaignPayoutRecovery($voucher)) {
            return new ClaimWorkflowDescriptorData(
                key: 'campaign.payout-recovery.v1',
                requires_mobile: true,
                requires_destination: true,
                requires_amount: false,
                title: 'Claim Your Protected Pay Code',
                description: 'Verify the beneficiary mobile and provide a corrected payout destination.',
                confirmation_label: 'Send to Corrected Destination',
                authentication_mode: ClaimAuthenticationMode::ClaimantHandoff,
                required_claim_fields: ['mobile', 'otp'],
                review: [
                    'payout_recovery' => true,
                    'fixed_amount' => true,
                ],
            );
        }

        if ($driver === 'campaign_worksheet_authorization') {
            $metadata = $voucher->getAttribute('metadata');

            return new ClaimWorkflowDescriptorData(
                key: 'campaign.officer-authorization.v1',
                requires_mobile: true,
                requires_destination: false,
                requires_amount: false,
                title: 'Campaign Officer Authorization',
                description: $this->campaignDescription($voucher),
                confirmation_label: 'Authorize Campaign',
                authentication_mode: ClaimAuthenticationMode::AuthenticatedOfficer,
                required_claim_fields: ['mobile'],
                skip_form_flow_splash: true,
                review: [
                    'authorization_reference' => data_get($metadata, 'instructions.execution.metadata.authorization_reference'),
                    'worksheet_reference' => data_get($metadata, 'instructions.execution.metadata.worksheet_reference'),
                    'beneficiary_count' => data_get($metadata, 'instructions.execution.metadata.beneficiary_count'),
                    'principal_minor' => data_get($metadata, 'instructions.execution.metadata.principal_minor'),
                    'currency' => data_get($metadata, 'instructions.execution.metadata.currency'),
                ],
            );
        }

        if ($driver === OnboardingVoucherInstructionPolicy::ExecutionDriver) {
            return $this->onboardingWorkflow($voucher);
        }

        if ($driver === 'stored_value') {
            return new ClaimWorkflowDescriptorData(
                key: 'stored-value.activation.v1',
                requires_mobile: true,
                requires_destination: false,
                requires_amount: false,
                title: 'Activate Reusable Balance',
                description: 'Sign in and verify your mobile number to bind this reusable balance to your Account.',
                confirmation_label: 'Activate My Balance',
                authentication_mode: ClaimAuthenticationMode::ClaimantHandoff,
                required_claim_fields: ['mobile', 'otp'],
                review: ['stored_value_activation' => true],
            );
        }

        if ($this->defaultOutcome($voucher) === 'account_funding') {
            return new ClaimWorkflowDescriptorData(
                key: 'account-funding.v1',
                requires_mobile: true,
                requires_destination: false,
                requires_amount: false,
                title: 'Add Funds to Your Account',
                description: 'Confirm your identity to add this Pay Code to your x-change Account.',
                confirmation_label: 'Add to My Account',
                authentication_mode: ClaimAuthenticationMode::ClaimantHandoff,
                required_claim_fields: ['mobile'],
                review: [
                    'account_funding' => true,
                    'completion_destination' => 'cockpit',
                ],
            );
        }

        if ($this->defaultOutcome($voucher) === 'lead_intake') {
            return new ClaimWorkflowDescriptorData(
                key: 'lead-intake.v1',
                requires_mobile: true,
                requires_destination: false,
                requires_amount: false,
                title: 'Submit Application',
                description: 'Provide your details so the merchant can prepare the next step.',
                confirmation_label: 'Submit Application',
                confirmation_title: 'Review your application',
                authentication_mode: ClaimAuthenticationMode::ClaimantHandoff,
                required_claim_fields: ['name', 'mobile', 'email'],
                review: [
                    'lead_intake' => true,
                    'completion_destination' => 'claim_success',
                ],
            );
        }

        return new ClaimWorkflowDescriptorData(
            key: 'disbursement.v1',
            requires_mobile: true,
            requires_destination: true,
            requires_amount: true,
            title: 'Disbursement Details',
            description: 'Provide the destination for this Pay Code.',
            confirmation_label: 'Confirm Redemption',
            required_claim_fields: ['mobile'],
        );
    }

    private function onboardingWorkflow(Voucher $voucher): ClaimWorkflowDescriptorData
    {
        $metadata = $voucher->getAttribute('metadata');
        $mobileVerificationRequired = (bool) data_get(
            $metadata,
            'instructions.execution.metadata.onboarding.mobile_verification_required',
            true,
        );

        return new ClaimWorkflowDescriptorData(
            key: OnboardingVoucherInstructionPolicy::WorkflowKey,
            requires_mobile: true,
            requires_destination: false,
            requires_amount: false,
            title: 'Accept Invitation',
            description: 'Enter your details to create your account and continue to the workspace.',
            confirmation_label: 'Create my account',
            confirmation_title: 'Review your details',
            authentication_mode: ClaimAuthenticationMode::ClaimantHandoff,
            required_claim_fields: ['full_name', 'email', 'mobile'],
            review: [
                'onboarding' => true,
                'recipient_name_required' => true,
                'recipient_email_required' => true,
                'mobile_verification_required' => $mobileVerificationRequired,
                'completion_destination' => 'cockpit',
            ],
        );
    }

    private function executionDriver(Voucher $voucher): ?string
    {
        return data_get($voucher->getAttribute('metadata'), 'instructions.execution.driver');
    }

    /**
     * Validate declared intent before applying legacy precedence. Null signals
     * retain their existing defaults; unknown signals must never become payout.
     */
    private function assertSupportedIntent(Voucher $voucher): void
    {
        $metadata = $voucher->getAttribute('metadata');
        $driver = data_get($metadata, 'instructions.execution.driver');
        $outcome = data_get($metadata, 'instructions.claim.default_outcome');
        $ordinaryOutcomes = [null, 'provider_disbursement', 'account_funding', 'lead_intake'];
        $allowed = [
            'default' => $ordinaryOutcomes,
            'x_change_live_cash' => $ordinaryOutcomes,
            'settlement_envelope' => $ordinaryOutcomes,
            'payable_collection' => $ordinaryOutcomes,
            'x_change_provider_funding' => [null],
            'x_change_account_funding' => [null],
            OnboardingVoucherInstructionPolicy::ExecutionDriver => [null, 'provider_disbursement', 'account_funding'],
            'stored_value' => [null, 'provider_disbursement'],
            'campaign_worksheet_authorization' => [null, 'authorize_campaign'],
            CampaignCoverageCompletionExecutionDriver::Key => [null, 'envelope_completion'],
        ];

        if ($driver !== null && (! is_string($driver) || ! array_key_exists($driver, $allowed))) {
            throw ValidationException::withMessages([
                'instructions.execution.driver' => 'This execution driver has no supported claim journey.',
            ]);
        }

        if (! in_array($outcome, $allowed[$driver ?? 'default'], true)) {
            throw ValidationException::withMessages([
                'instructions.claim.default_outcome' => 'The declared claim outcome is unsupported or conflicts with its execution driver.',
            ]);
        }

        if ($this->isCampaignPayoutRecovery($voucher)
            && (! in_array($driver, [null, 'default', 'x_change_live_cash', 'settlement_envelope'], true)
                || ! in_array($outcome, [null, 'provider_disbursement'], true))) {
            throw ValidationException::withMessages([
                'instructions.execution.driver' => 'Payout recovery cannot be combined with a different claim journey.',
            ]);
        }
    }

    private function defaultOutcome(Voucher $voucher): ?string
    {
        return data_get($voucher->getAttribute('metadata'), 'instructions.claim.default_outcome');
    }

    private function isCampaignPayoutRecovery(Voucher $voucher): bool
    {
        $metadata = $voucher->getAttribute('metadata');

        return data_get($metadata, 'instructions.metadata.custom.campaign.claim_activation') === 'provider_rejection'
            && data_get($metadata, 'treasury.pay_code_reservation.status') === 'recovery_pending';
    }

    private function campaignDescription(Voucher $voucher): string
    {
        $metadata = $voucher->getAttribute('metadata');
        $beneficiaryCount = (int) data_get($metadata, 'instructions.execution.metadata.beneficiary_count', 0);
        $currency = (string) data_get($metadata, 'instructions.execution.metadata.currency', 'PHP');
        $principalMinor = (int) data_get($metadata, 'instructions.execution.metadata.principal_minor', 0);

        return sprintf(
            'Review the frozen worksheet for %d %s totaling %s %s. No payout will be sent by this approval.',
            $beneficiaryCount,
            $beneficiaryCount === 1 ? 'beneficiary' : 'beneficiaries',
            number_format($principalMinor / 100, 2),
            $currency,
        );
    }
}
