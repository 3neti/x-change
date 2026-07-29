<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Claim;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\ClaimWorkflowResolverContract;
use LBHurtado\XChange\Data\Claim\ClaimWorkflowDescriptorData;

final class DefaultClaimWorkflowResolver implements ClaimWorkflowResolverContract
{
    public function resolve(Voucher $voucher): ClaimWorkflowDescriptorData
    {
        if ($this->executionDriver($voucher) === 'campaign_worksheet_authorization') {
            $metadata = $voucher->getAttribute('metadata');

            return new ClaimWorkflowDescriptorData(
                key: 'campaign.officer-authorization.v1',
                requires_mobile: true,
                requires_destination: false,
                requires_amount: false,
                requires_authenticated_officer: true,
                title: 'Campaign Officer Authorization',
                description: $this->campaignDescription($voucher),
                review: [
                    'authorization_reference' => data_get($metadata, 'instructions.execution.metadata.authorization_reference'),
                    'worksheet_reference' => data_get($metadata, 'instructions.execution.metadata.worksheet_reference'),
                    'beneficiary_count' => data_get($metadata, 'instructions.execution.metadata.beneficiary_count'),
                    'principal_minor' => data_get($metadata, 'instructions.execution.metadata.principal_minor'),
                    'currency' => data_get($metadata, 'instructions.execution.metadata.currency'),
                ],
            );
        }

        return new ClaimWorkflowDescriptorData(
            key: 'disbursement.v1',
            requires_mobile: true,
            requires_destination: true,
            requires_amount: true,
            requires_authenticated_officer: false,
            title: 'Disbursement Details',
            description: 'Provide the destination for this Pay Code.',
        );
    }

    private function executionDriver(Voucher $voucher): ?string
    {
        return data_get($voucher->getAttribute('metadata'), 'instructions.execution.driver');
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
