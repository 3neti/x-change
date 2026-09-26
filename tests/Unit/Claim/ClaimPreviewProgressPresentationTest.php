<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use LBHurtado\XChange\Data\Claim\ClaimWorkflowDescriptorData;
use LBHurtado\XChange\Data\Claim\ClaimWorkflowInterpretationData;
use LBHurtado\XChange\Enums\ClaimPreviewProgressState;
use LBHurtado\XChange\Services\Claim\ClaimPreviewProgressPresentation;
use LBHurtado\XChange\Services\Claim\CoverageCompletionSuccessPresentation;

function previewWorkflow(string $key): ClaimWorkflowInterpretationData
{
    return ClaimWorkflowInterpretationData::resolved(new ClaimWorkflowDescriptorData(
        key: $key,
        requires_mobile: true,
        requires_destination: false,
        requires_amount: false,
        title: 'Claim journey',
        description: 'Claim journey description.',
        confirmation_label: 'Continue',
    ));
}

it('uses the live coverage completion presentation copy for simulated progress', function (
    ClaimPreviewProgressState $state,
): void {
    $preview = app(ClaimPreviewProgressPresentation::class)->project(
        previewWorkflow('campaign.coverage-completion.v1'),
        $state,
    );
    $live = app(CoverageCompletionSuccessPresentation::class)->forState($state->value);

    expect($preview['mode'])->toBe('simulated')
        ->and($preview['verified_live_outcome'])->toBeFalse()
        ->and($preview['workflow_key'])->toBe('campaign.coverage-completion.v1')
        ->and($preview['presentation'])->toBe($live);
})->with([
    ClaimPreviewProgressState::DetailsRequired,
    ClaimPreviewProgressState::Processing,
    ClaimPreviewProgressState::Ready,
    ClaimPreviewProgressState::NeedsAttention,
]);

it('keeps payment outstanding simulation on the intake journey', function (): void {
    $preview = app(ClaimPreviewProgressPresentation::class)->project(
        previewWorkflow('lead-intake.v1'),
        ClaimPreviewProgressState::PaymentOutstanding,
    );

    expect(data_get($preview, 'presentation.state'))->toBe('payment_outstanding')
        ->and(data_get($preview, 'presentation.title_template'))->toBe('Application received')
        ->and($preview['allowed_states'])->toBe(['current', 'payment_outstanding']);
});

it('does not let a requested simulation override classifier attention', function (): void {
    $preview = app(ClaimPreviewProgressPresentation::class)->project(
        ClaimWorkflowInterpretationData::needsAttention(),
        ClaimPreviewProgressState::Ready,
    );

    expect($preview['state'])->toBe('needs_attention')
        ->and($preview['attention_source'])->toBe('workflow_interpretation')
        ->and($preview['allowed_states'])->toBe(['current'])
        ->and(data_get($preview, 'presentation.title_template'))->toBe('Claim journey needs attention');
});

it('rejects progress states that are incompatible with the resolved journey', function (): void {
    app(ClaimPreviewProgressPresentation::class)->project(
        previewWorkflow('disbursement.v1'),
        ClaimPreviewProgressState::Ready,
    );
})->throws(ValidationException::class);
