<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Exceptions;

use DomainException;

final class CommercialEconomicsUnavailable extends DomainException
{
    public const Code = 'COMMERCIAL_ECONOMICS_UNAVAILABLE';

    public const Message = 'Pricing is temporarily unavailable. Please try again shortly.';

    public readonly string $diagnostic;

    public function __construct(string $diagnostic = self::Message)
    {
        $this->diagnostic = $diagnostic;
        parent::__construct(self::Message);
    }
}
