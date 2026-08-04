<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum ClaimEvidenceKind: string
{
    case Text = 'text';
    case Location = 'location';
    case Image = 'image';
    case Verification = 'verification';
    case Document = 'document';
}
