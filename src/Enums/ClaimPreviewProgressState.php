<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum ClaimPreviewProgressState: string
{
    case Current = 'current';
    case PaymentOutstanding = 'payment_outstanding';
    case DetailsRequired = 'details_required';
    case Processing = 'processing';
    case Ready = 'ready';
    case NeedsAttention = 'needs_attention';
}
