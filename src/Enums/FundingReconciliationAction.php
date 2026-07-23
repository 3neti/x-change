<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum FundingReconciliationAction: string
{
    case RetryVerification = 'retry_verification';
    case MatchVerifiedObservation = 'match_verified_observation';
    case CompensateVerifiedPosting = 'compensate_verified_posting';
}
