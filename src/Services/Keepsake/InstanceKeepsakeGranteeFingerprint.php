<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Keepsake;

use Illuminate\Database\Eloquent\Model;

final class InstanceKeepsakeGranteeFingerprint
{
    public function for(Model $grantee): string
    {
        $createdAt = $grantee->getAttribute('created_at');

        return hash('sha256', implode('|', [
            $grantee->getMorphClass(),
            (string) $grantee->getKey(),
            mb_strtolower(trim((string) $grantee->getAttribute('email'))),
            $createdAt instanceof \DateTimeInterface ? $createdAt->format(DATE_ATOM) : (string) $createdAt,
        ]));
    }
}
