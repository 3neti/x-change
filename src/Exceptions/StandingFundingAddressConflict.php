<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Exceptions;

use InvalidArgumentException;

final class StandingFundingAddressConflict extends InvalidArgumentException
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'standing_funding_address_conflict',
        public readonly ?string $operatorReference = null,
    ) {
        parent::__construct($message);
    }

    public static function alreadyBound(): self
    {
        return new self(
            'This QR Ph funding address is already bound to a different funding ledger. Funding is blocked until an operator reconciles the existing binding.',
        );
    }

    public static function migrationRequired(string $operatorReference): self
    {
        return new self(
            'This QR Ph funding address requires an approved ledger-binding migration before it can be used again.',
            'standing_funding_address_binding_migration_required',
            $operatorReference,
        );
    }
}
