<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum IssuanceBatchStatus: string
{
    case Draft = 'draft';
    case AwaitingPayment = 'awaiting_payment';
    case Funded = 'funded';
    case Issuing = 'issuing';
    case PartiallyIssued = 'partially_issued';
    case Issued = 'issued';
    case Attention = 'attention';
    case Cancelled = 'cancelled';
}
