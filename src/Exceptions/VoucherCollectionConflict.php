<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Exceptions;

use RuntimeException;

class VoucherCollectionConflict extends RuntimeException
{
    public static function forIdempotencyKey(string $key): self
    {
        return new self("Voucher collection idempotency conflict for key [{$key}].");
    }

    public static function forProviderReference(string $provider, string $reference): self
    {
        return new self("Voucher collection provider reference conflict for [{$provider}:{$reference}].");
    }
}
