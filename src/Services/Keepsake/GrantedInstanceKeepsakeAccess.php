<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Keepsake;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\XChange\Contracts\Keepsake\InstanceKeepsakeAccessContract;

final class GrantedInstanceKeepsakeAccess implements InstanceKeepsakeAccessContract
{
    public function __construct(private readonly InstanceKeepsakeGranteeFingerprint $fingerprints) {}

    public function canDownload(Authenticatable $actor, array $grant): bool
    {
        return $actor instanceof Model
            && hash_equals((string) ($grant['grantee_type'] ?? ''), $actor->getMorphClass())
            && hash_equals((string) ($grant['grantee_id'] ?? ''), (string) $actor->getKey())
            && hash_equals(
                (string) ($grant['grantee_fingerprint'] ?? ''),
                $this->fingerprints->for($actor),
            );
    }
}
