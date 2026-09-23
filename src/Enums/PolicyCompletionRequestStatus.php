<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum PolicyCompletionRequestStatus: string
{
    case AwaitingApproval = 'awaiting_approval';
    case Authorized = 'authorized';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Indeterminate = 'indeterminate';

    public function terminal(): bool
    {
        return in_array($this, [self::Succeeded, self::Failed, self::Indeterminate], true);
    }
}
