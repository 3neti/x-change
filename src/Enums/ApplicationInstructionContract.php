<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum ApplicationInstructionContract: string
{
    case Voucher = 'voucher';
    case StoredValueCollection = 'stored_value_collection';
    case Record = 'record';
    case None = 'none';
}
