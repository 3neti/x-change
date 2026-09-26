<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Claim;

use Illuminate\Validation\ValidationException;
use LBHurtado\XChange\Data\Claim\ClaimWorkflowInterpretationData;
use LBHurtado\XChange\Enums\ClaimPreviewProgressState;

final class ClaimPreviewProgressPresentation
{
    public function __construct(
        private readonly CoverageCompletionSuccessPresentation $coverageCompletion,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function project(
        ClaimWorkflowInterpretationData $interpretation,
        ClaimPreviewProgressState $requestedState,
    ): array {
        if ($interpretation->requiresAttention()) {
            return $this->simulation(
                state: ClaimPreviewProgressState::NeedsAttention,
                interpretation: $interpretation,
                presentation: [
                    'state' => 'needs_attention',
                    'title_template' => $interpretation->attention_label,
                    'body' => $interpretation->attention_message,
                    'suppress_legacy_rider' => true,
                ],
                allowedStates: [ClaimPreviewProgressState::Current],
                attentionSource: 'workflow_interpretation',
            );
        }

        $workflowKey = $interpretation->workflow?->key;
        $allowedStates = $this->allowedStates($workflowKey);

        if (! in_array($requestedState, $allowedStates, true)) {
            throw ValidationException::withMessages([
                'preview_state' => 'The selected simulated progress state is not available for this claim journey.',
            ]);
        }

        if ($requestedState === ClaimPreviewProgressState::Current) {
            return $this->simulation(
                state: $requestedState,
                interpretation: $interpretation,
                presentation: null,
                allowedStates: $allowedStates,
            );
        }

        $presentation = $requestedState === ClaimPreviewProgressState::PaymentOutstanding
            ? [
                'state' => 'payment_outstanding',
                'title_template' => 'Application received',
                'body' => 'The application details are recorded. Payment is still required before fulfillment can continue.',
                'suppress_legacy_rider' => true,
            ]
            : $this->coverageCompletion->forState($requestedState->value);

        return $this->simulation(
            state: $requestedState,
            interpretation: $interpretation,
            presentation: $presentation,
            allowedStates: $allowedStates,
            attentionSource: $requestedState === ClaimPreviewProgressState::NeedsAttention
                ? 'simulated_progress'
                : null,
        );
    }

    /**
     * @return list<ClaimPreviewProgressState>
     */
    private function allowedStates(?string $workflowKey): array
    {
        return match ($workflowKey) {
            'lead-intake.v1' => [
                ClaimPreviewProgressState::Current,
                ClaimPreviewProgressState::PaymentOutstanding,
            ],
            'campaign.coverage-completion.v1' => [
                ClaimPreviewProgressState::Current,
                ClaimPreviewProgressState::DetailsRequired,
                ClaimPreviewProgressState::Processing,
                ClaimPreviewProgressState::Ready,
                ClaimPreviewProgressState::NeedsAttention,
            ],
            default => [ClaimPreviewProgressState::Current],
        };
    }

    /**
     * @param  array<string, mixed>|null  $presentation
     * @param  list<ClaimPreviewProgressState>  $allowedStates
     * @return array<string, mixed>
     */
    private function simulation(
        ClaimPreviewProgressState $state,
        ClaimWorkflowInterpretationData $interpretation,
        ?array $presentation,
        array $allowedStates,
        ?string $attentionSource = null,
    ): array {
        return array_filter([
            'schema' => 'x-change.claim-preview-simulation.v1',
            'mode' => 'simulated',
            'state' => $state->value,
            'verified_live_outcome' => false,
            'source' => 'issuer_selected_preview',
            'workflow_state' => $interpretation->state->value,
            'workflow_key' => $interpretation->workflow?->key,
            'allowed_states' => array_map(
                static fn (ClaimPreviewProgressState $allowedState): string => $allowedState->value,
                $allowedStates,
            ),
            'attention_source' => $attentionSource,
            'presentation' => $presentation,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
