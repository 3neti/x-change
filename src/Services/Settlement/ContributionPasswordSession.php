<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Settlement;

use Illuminate\Contracts\Session\Session;
use LBHurtado\SettlementEnvelope\Models\EnvelopeContributionToken;

final class ContributionPasswordSession
{
    public function verify(EnvelopeContributionToken $token, string $password, Session $session): bool
    {
        if (! $token->exists || $token->getKey() === null) {
            return false;
        }
        $session->forget($this->key($token));
        if (! $token->isValid() || ! $token->requiresPassword() || ! $token->verifyPassword($password)) {
            return false;
        }

        $session->put($this->key($token), $this->fingerprint($token));

        return true;
    }

    public function isVerified(EnvelopeContributionToken $token, Session $session): bool
    {
        if (! $token->exists || $token->getKey() === null || ! $token->isValid()) {
            return false;
        }
        if (! $token->requiresPassword()) {
            return true;
        }

        $stored = $session->get($this->key($token));

        return is_string($stored) && hash_equals($this->fingerprint($token), $stored);
    }

    private function key(EnvelopeContributionToken $token): string
    {
        return 'x-change.contribution.verified.'.(string) $token->getKey();
    }

    private function fingerprint(EnvelopeContributionToken $token): string
    {
        return hash('sha256', (string) $token->getKey()."\0".(string) $token->password);
    }
}
