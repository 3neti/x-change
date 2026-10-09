<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Claim;

use LBHurtado\FormFlowManager\Data\FormFlowInstructionsData;
use LBHurtado\FormFlowManager\Services\DriverService;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\ClaimWorkflowResolverContract;
use LBHurtado\XChange\Data\Claim\CompiledVoucherClaimFlowData;
use LBHurtado\XChange\Services\Settlement\CampaignPaymentPayerName;
use LBHurtado\XChange\Services\SettlementRailResolver;
use LBHurtado\XChange\Support\Claim\ClaimExperiencePayload;
use LBHurtado\XChange\Support\Claim\FormFlowSplashSkipPolicy;

final class VoucherClaimFlowCompiler
{
    public function __construct(
        private readonly DriverService $drivers,
        private readonly CampaignPaymentFormFlowDriver $campaignPaymentDrivers,
        private readonly ClaimExperienceCompiler $experiences,
        private readonly ClaimWorkflowResolverContract $workflows,
        private readonly FormFlowClaimWorkflowMutator $formFlowWorkflows,
        private readonly FormFlowSplashSkipPolicy $splashPolicy,
        private readonly SettlementRailResolver $settlementRails,
        private readonly CampaignPaymentPayerName $payerNames,
    ) {}

    public function compile(
        Voucher $voucher,
        ?string $authenticatedMobile = null,
        ?float $payoutAmount = null,
    ): CompiledVoucherClaimFlowData {
        $experience = $this->experiences->compile($voucher);
        $workflow = $this->workflows->resolve($voucher);
        $payload = ClaimExperiencePayload::putIntoInstructions(
            ($workflow->key === 'campaign.coverage-completion.v1'
                ? $this->campaignPaymentDrivers
                : $this->drivers)->transform($voucher)->toArray(),
            $experience->toArray(),
        );
        $instructions = $this->formFlowWorkflows->apply(
            FormFlowInstructionsData::from($payload),
            $workflow,
            $authenticatedMobile,
            $this->settlementRails->forVoucher(
                $voucher,
                $payoutAmount ?? (float) data_get($voucher->instructions, 'cash.amount', 0),
            )->value,
        );

        $instructionPayload = $this->prefillCampaignPayerName($voucher, $instructions->toArray());

        return new CompiledVoucherClaimFlowData(
            experience: $experience,
            workflow: $workflow,
            instructions: FormFlowInstructionsData::from(
                $this->splashPolicy->apply($instructionPayload),
            ),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function prefillCampaignPayerName(Voucher $voucher, array $payload): array
    {
        $requiredInputs = array_map(
            static fn (mixed $field): string => $field instanceof \BackedEnum ? (string) $field->value : (string) $field,
            (array) data_get($voucher->metadata, 'instructions.inputs.fields', []),
        );

        if (in_array('kyc', $requiredInputs, true)) {
            return $payload;
        }

        $name = $this->payerNames->forCompletionVoucher($voucher);

        if ($name === null) {
            return $payload;
        }

        foreach ((array) ($payload['steps'] ?? []) as $stepIndex => $step) {
            if (data_get($step, 'config.step_name') !== 'bio_fields') {
                continue;
            }

            foreach ((array) data_get($step, 'config.fields', []) as $fieldIndex => $field) {
                if (data_get($field, 'name') === 'full_name') {
                    data_set($payload, "steps.{$stepIndex}.config.fields.{$fieldIndex}.default", $name);
                    data_set($payload, "steps.{$stepIndex}.config.fields.{$fieldIndex}.help_text", 'Suggested from the payment sender. Confirm or edit your full name.');

                    return $payload;
                }
            }
        }

        return $payload;
    }
}
