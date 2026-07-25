<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum FundingRequestStatus: string
{
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case NeedsInformation = 'needs_information';
    case AwaitingApproval = 'awaiting_approval';
    case PayCodeIssued = 'funding_code_issued';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';
    case Expired = 'expired';
}
