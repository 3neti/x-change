<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

final class PayCodeIssuanceBusy extends RuntimeException implements ShouldntReport
{
    public const string Message = 'Pay Code issuance is temporarily busy. No Pay Code was issued. Please try again.';

    public function __construct()
    {
        parent::__construct(self::Message);
    }
}
