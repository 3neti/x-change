<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\PublicIssuance;

use LBHurtado\XChange\Actions\PayCode\EstimatePayCodeCost;
use LBHurtado\XChange\Data\PublicIssuance\PublicIssuanceEstimateData;

final readonly class PublicIssuanceEstimateService
{
    public function __construct(
        private PublicIssuanceInput $input,
        private PublicIssuancePayloadFactory $payloads,
        private PublicIssuanceInstructionPolicy $policy,
        private EstimatePayCodeCost $estimator,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function estimate(array $input): PublicIssuanceEstimateData
    {
        $validated = $this->input->validate($input);
        $payload = $this->payloads->make($validated['amount_minor'], $validated['currency']);
        $this->policy->assertAllowed($payload);
        $estimate = $this->estimator->handle($payload);

        return new PublicIssuanceEstimateData(
            schema: 'x-change.public-issuance-estimate.v1',
            currency: $estimate->currency,
            principal_minor: $validated['amount_minor'],
            service_fees_minor: $estimate->customer_charge_minor,
            total_required_minor: (int) round(((float) $estimate->account_debit) * 100),
            billing_mode: $estimate->billing_mode,
            components: $estimate->components,
            authoritative: true,
            creates_order: false,
        );
    }
}
