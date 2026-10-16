<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum FundingReconciliationAction: string
{
    case RetryVerification = 'retry_verification';
    case MatchVerifiedObservation = 'match_verified_observation';
    case RecoverPaidOnDemandIssuance = 'recover_paid_on_demand_issuance';
    case CompensateVerifiedPosting = 'compensate_verified_posting';
}
