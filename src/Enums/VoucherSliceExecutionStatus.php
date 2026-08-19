<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum VoucherSliceExecutionStatus: string
{
    case Reserved = 'reserved';
    case Executing = 'executing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Indeterminate = 'indeterminate';
}
