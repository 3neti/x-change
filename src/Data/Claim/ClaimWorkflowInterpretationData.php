<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Claim;

use LBHurtado\XChange\Enums\ClaimWorkflowInterpretationState;
use Spatie\LaravelData\Data;

final class ClaimWorkflowInterpretationData extends Data
{
    public function __construct(
        public ClaimWorkflowInterpretationState $state,
        public ?ClaimWorkflowDescriptorData $workflow = null,
        public ?string $attention_key = null,
        public ?string $attention_label = null,
        public ?string $attention_message = null,
    ) {}

    public static function resolved(ClaimWorkflowDescriptorData $workflow): self
    {
        return new self(
            state: ClaimWorkflowInterpretationState::Resolved,
            workflow: $workflow,
        );
    }

    public static function needsAttention(): self
    {
        return new self(
            state: ClaimWorkflowInterpretationState::NeedsAttention,
            attention_key: 'unsupported_claim_journey',
            attention_label: 'Claim journey needs attention',
            attention_message: 'This Pay Code has conflicting or unsupported claim instructions. Contact the issuer before continuing.',
        );
    }

    public function requiresAttention(): bool
    {
        return $this->state === ClaimWorkflowInterpretationState::NeedsAttention;
    }
}
