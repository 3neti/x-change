<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Banking;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use LBHurtado\XChange\Models\BankAccountAssignment;
use LBHurtado\XChange\Models\ProviderAccountLink;

final class SynchronizeNetbankAccountIdentities
{
    public function __construct(
        private readonly NetbankAccountAssignmentPreflight $preflight,
        private readonly BankAccountIdentityRegistry $identities,
    ) {}

    public function synchronize(): void
    {
        if (! $this->preflight->inspect()['ready']) {
            throw ValidationException::withMessages(['account_number' => 'Account records need reconciliation before another account can be assigned.']);
        }

        foreach (ProviderAccountLink::query()->where('provider', 'netbank')->where('topology', 'dedicated')->cursor() as $link) {
            $this->claim($link->owner, $link->routing_profile_ciphertext['bank_account_number'] ?? null);
        }

        foreach (BankAccountAssignment::query()->where('provider', 'netbank')->cursor() as $assignment) {
            $this->claim($assignment->owner, $assignment->details_ciphertext['account_number'] ?? null);
        }
    }

    private function claim(mixed $owner, mixed $accountNumber): void
    {
        if (! $owner instanceof Model || ! is_string($accountNumber)) {
            throw ValidationException::withMessages(['account_number' => 'Account records need reconciliation before another account can be assigned.']);
        }

        $this->identities->claim($owner, 'netbank', $accountNumber);
    }
}
