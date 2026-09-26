<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum ClaimWorkflowInterpretationState: string
{
    case Resolved = 'resolved';
    case NeedsAttention = 'needs_attention';
}
