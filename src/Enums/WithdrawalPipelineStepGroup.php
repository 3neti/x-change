<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum WithdrawalPipelineStepGroup: string
{
    case PRE_AUTH = 'pre_auth';
    case CASH_DOMAIN = 'cash_domain';
    case INTEGRATION = 'integration';
    case EXECUTION = 'execution';
    case SETTLEMENT = 'settlement';
    case RESULT = 'result';
}
