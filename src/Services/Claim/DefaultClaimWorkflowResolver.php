<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Claim;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\ClaimWorkflowResolverContract;
use LBHurtado\XChange\Data\Claim\ClaimWorkflowDescriptorData;
use LBHurtado\XChange\Enums\ClaimAuthenticationMode;
use LBHurtado\XChange\Services\OnboardingVoucherInstructionPolicy;

final class DefaultClaimWorkflowResolver implements ClaimWorkflowResolverContract
{
    public function resolve(Voucher $voucher): ClaimWorkflowDescriptorData
    {
        $driver = $this->executionDriver($voucher);

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
            title: 'Set Up Your Account',
            description: 'Confirm your details to receive this Pay Code in your x-change Account.',
            confirmation_label: 'Create My Account',
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

    private function defaultOutcome(Voucher $voucher): ?string
    {
        return data_get($voucher->getAttribute('metadata'), 'instructions.claim.default_outcome');
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
