<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Banking;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LBHurtado\XChange\Models\BankAccountAssignment;
use LBHurtado\XChange\Models\ProviderAccountLink;
use Throwable;

/** Read-only inventory; account numbers and owner identities are not returned. */
final class NetbankAccountAssignmentPreflight
{
    public function assertAssignable(Model $owner, string $accountNumber): void
    {
        if (! $this->inspect()['ready']) {
            throw ValidationException::withMessages([
                'account_number' => 'Account records need reconciliation before another account can be assigned.',
            ]);
        }

        foreach (ProviderAccountLink::query()->where('provider', 'netbank')->where('topology', 'dedicated')->cursor() as $link) {
            $number = preg_replace('/\D+/', '', (string) ($link->routing_profile_ciphertext['bank_account_number'] ?? ''));
            if ($number === $accountNumber && ($link->owner_type !== $owner->getMorphClass() || (string) $link->owner_id !== (string) $owner->getKey())) {
                throw ValidationException::withMessages(['account_number' => 'This account is already assigned.']);
            }
        }
    }

    /** @return array{ready: bool, assignments: int, links: int, duplicate_accounts: int, duplicate_aliases: int, unreadable_records: int} */
    public function inspect(): array
    {
        $groups = [];
        $aliases = [];
        $counts = ['assignments' => 0, 'links' => 0, 'unreadable_records' => 0];
        $sharedAlias = config('payment-gateway.netbank.funding.vca_alias');
        $sharedNumber = config('payment-gateway.netbank.funding.corporate_account_number');
        $normalizedSharedNumber = is_string($sharedNumber) ? preg_replace('/\D+/', '', $sharedNumber) : '';
        if (is_string($sharedAlias) && preg_match('/\A[0-9]{5}\z/', $sharedAlias)
            && is_string($normalizedSharedNumber) && strlen($normalizedSharedNumber) >= 8 && strlen($normalizedSharedNumber) <= 32) {
            $this->addAlias($aliases, $counts, $sharedAlias, $normalizedSharedNumber);
        }

        foreach (BankAccountAssignment::query()->where('provider', 'netbank')->cursor() as $assignment) {
            $counts['assignments']++;
            try {
                $details = $assignment->details_ciphertext;
                $this->add($groups, $counts, $details['account_number'] ?? null, 'assignment', $assignment->owner_type, $assignment->owner_id);
                $this->addAlias($aliases, $counts, $details['vca_alias'] ?? null, $details['account_number'] ?? null);
            } catch (Throwable) {
                $counts['unreadable_records']++;
            }
        }

        foreach (ProviderAccountLink::query()->where('provider', 'netbank')->where('topology', 'dedicated')->cursor() as $link) {
            $counts['links']++;
            try {
                $details = $link->routing_profile_ciphertext;
                $this->add($groups, $counts, $details['bank_account_number'] ?? null, 'link', $link->owner_type, $link->owner_id);
                $this->addAlias($aliases, $counts, $details['vca_alias'] ?? null, $details['bank_account_number'] ?? null);
            } catch (Throwable) {
                $counts['unreadable_records']++;
            }
        }

        $duplicates = 0;
        foreach ($groups as $group) {
            if (count($group['owners']) > 1 || $group['assignment'] > 1 || $group['link'] > 1) {
                $duplicates++;
            }
        }

        $duplicateAliases = count(array_filter($aliases, static fn (array $accounts): bool => count($accounts) > 1));

        return [
            'ready' => $duplicates === 0 && $duplicateAliases === 0 && $counts['unreadable_records'] === 0,
            'assignments' => $counts['assignments'],
            'links' => $counts['links'],
            'duplicate_accounts' => $duplicates,
            'duplicate_aliases' => $duplicateAliases,
            'unreadable_records' => $counts['unreadable_records'],
        ];
    }

    /** @param array<string, array<string, bool>> $aliases @param array<string, int> $counts */
    private function addAlias(array &$aliases, array &$counts, mixed $alias, mixed $number): void
    {
        if ($alias === null || $alias === '') {
            return;
        }

        $normalized = is_string($number) ? preg_replace('/\D+/', '', $number) : '';
        if (! is_string($alias) || ! preg_match('/\A[0-9]{5}\z/', $alias)
            || ! is_string($normalized) || strlen($normalized) < 8 || strlen($normalized) > 32) {
            $counts['unreadable_records']++;

            return;
        }

        $aliases[hash('sha256', $alias)][hash('sha256', $normalized)] = true;
    }

    /** @param array<string, mixed> $groups @param array<string, int> $counts */
    private function add(array &$groups, array &$counts, mixed $number, string $type, mixed $ownerType, mixed $ownerId): void
    {
        $normalized = is_string($number) ? preg_replace('/\D+/', '', $number) : '';
        if (! is_string($normalized) || strlen($normalized) < 8 || strlen($normalized) > 32 || ! is_string($ownerType) || $ownerType === '' || $ownerId === null) {
            $counts['unreadable_records']++;

            return;
        }

        $key = hash('sha256', $normalized);
        $groups[$key] ??= ['owners' => [], 'assignment' => 0, 'link' => 0];
        $groups[$key]['owners'][$ownerType.':'.$ownerId] = true;
        $groups[$key][$type]++;
    }

    public function assertAliasAssignable(string $alias, string $accountNumber): void
    {
        if (! preg_match('/\A[0-9]{5}\z/', $alias)) {
            throw ValidationException::withMessages(['vca_alias' => 'A five-digit NetBank alias is required.']);
        }

        DB::table('xchange_bank_identity_key_guard')->where('id', 1)->lockForUpdate()->first();

        if (hash_equals((string) config('payment-gateway.netbank.funding.vca_alias'), $alias)) {
            throw ValidationException::withMessages(['vca_alias' => 'This NetBank alias is already assigned.']);
        }

        foreach (BankAccountAssignment::query()->where('provider', 'netbank')->lockForUpdate()->cursor() as $assignment) {
            $details = $assignment->details_ciphertext;
            $this->assertAliasMatchesAccount($alias, $accountNumber, $details['vca_alias'] ?? null, $details['account_number'] ?? null);
        }

        foreach (ProviderAccountLink::query()->where('provider', 'netbank')->where('topology', 'dedicated')->lockForUpdate()->cursor() as $link) {
            $details = $link->routing_profile_ciphertext;
            $this->assertAliasMatchesAccount($alias, $accountNumber, $details['vca_alias'] ?? null, $details['bank_account_number'] ?? null);
        }
    }

    private function assertAliasMatchesAccount(string $alias, string $accountNumber, mixed $existingAlias, mixed $existingNumber): void
    {
        if ($existingAlias === null || $existingAlias === '') {
            return;
        }

        $normalizedNumber = preg_replace('/\D+/', '', (string) $existingNumber);
        if (! is_string($existingAlias) || ! preg_match('/\A[0-9]{5}\z/', $existingAlias)
            || ! is_string($normalizedNumber) || $normalizedNumber === '') {
            throw ValidationException::withMessages(['vca_alias' => 'Account routing records need reconciliation.']);
        }

        if (($existingAlias === $alias && $normalizedNumber !== $accountNumber)
            || ($normalizedNumber === $accountNumber && $existingAlias !== $alias)) {
            throw ValidationException::withMessages(['vca_alias' => 'This NetBank alias is already assigned or does not match the existing account.']);
        }
    }
}
