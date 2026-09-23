<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum PolicyCompletionOutcomeStatus: string
{
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Indeterminate = 'indeterminate';

    public function requestStatus(): PolicyCompletionRequestStatus
    {
        return PolicyCompletionRequestStatus::from($this->value);
    }
}
