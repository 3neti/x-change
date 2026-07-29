<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Claim;

use LBHurtado\FormFlowManager\Data\FormFlowInstructionsData;
use LBHurtado\XChange\Data\Claim\ClaimWorkflowDescriptorData;

final class FormFlowClaimWorkflowMutator
{
    public function apply(
        FormFlowInstructionsData $instructions,
        ClaimWorkflowDescriptorData $workflow,
        ?string $authenticatedMobile = null,
    ): FormFlowInstructionsData {
        return FormFlowInstructionsData::from($this->mutate(
            $instructions->toArray(),
            $workflow,
            $authenticatedMobile,
        ));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function mutate(
        array $payload,
        ClaimWorkflowDescriptorData $workflow,
        ?string $authenticatedMobile = null,
    ): array {

        $payload['title'] = $workflow->title;
        $payload['description'] = $workflow->description;
        $payload['metadata'] = array_replace_recursive((array) ($payload['metadata'] ?? []), [
            'claim_workflow' => [
                'key' => $workflow->key,
                'requires_mobile' => $workflow->requires_mobile,
                'requires_destination' => $workflow->requires_destination,
                'requires_amount' => $workflow->requires_amount,
                'requires_authenticated_officer' => $workflow->requires_authenticated_officer,
                'review' => $workflow->review,
            ],
        ]);

        $payload['steps'] = array_map(
            fn (array $step): array => $this->applyToStep($step, $workflow, $authenticatedMobile),
            (array) ($payload['steps'] ?? []),
        );

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $step
     * @return array<string, mixed>
     */
    private function applyToStep(
        array $step,
        ClaimWorkflowDescriptorData $workflow,
        ?string $authenticatedMobile,
    ): array {
        if (($step['config']['step_name'] ?? null) !== 'wallet_info') {
            return $step;
        }

        $step['config']['title'] = $workflow->title;
        $step['config']['description'] = $workflow->description;
        $step['config']['auto_sync'] = ['enabled' => false];
        $step['config']['fields'] = array_values(array_map(
            fn (array $field): array => $this->applyToField($field, $workflow, $authenticatedMobile),
            array_filter(
                (array) ($step['config']['fields'] ?? []),
                fn (array $field): bool => $this->shouldKeepField($field, $workflow),
            ),
        ));

        return $step;
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function shouldKeepField(array $field, ClaimWorkflowDescriptorData $workflow): bool
    {
        return match ($field['name'] ?? null) {
            'amount' => $workflow->requires_amount,
            'settlement_rail', 'bank_code', 'account_number' => $workflow->requires_destination,
            'mobile' => $workflow->requires_mobile,
            default => true,
        };
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array<string, mixed>
     */
    private function applyToField(
        array $field,
        ClaimWorkflowDescriptorData $workflow,
        ?string $authenticatedMobile,
    ): array {
        if (
            ($field['name'] ?? null) === 'mobile'
            && $workflow->requires_authenticated_officer
            && filled($authenticatedMobile)
        ) {
            $field['default'] = $authenticatedMobile;
            $field['readonly'] = true;
            $field['persist'] = false;
        }

        return $field;
    }
}
