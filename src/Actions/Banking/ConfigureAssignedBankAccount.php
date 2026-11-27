<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Banking;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LBHurtado\XChange\Contracts\AuditLoggerContract;
use LBHurtado\XChange\Models\BankAccountAssignment;
use LBHurtado\XChange\Services\Banking\AssignedBankTreasuryConnection;
use LBHurtado\XChange\Services\Banking\NetbankAccountAssignmentPreflight;
use LBHurtado\XChange\Services\CheckProviderSourceAccountReadiness;
use LBHurtado\XChange\Services\Funding\ReserveNetbankVca;
use RuntimeException;

final readonly class ConfigureAssignedBankAccount
{
    public function __construct(
        private AssignedBankTreasuryConnection $connections,
        private CheckProviderSourceAccountReadiness $readiness,
    ) {}

    public function handle(BankAccountAssignment $assignment, string $connectionReference, Model $actor): void
    {
        $owner = $assignment->owner;
        $details = $assignment->details_ciphertext;
        $accountNumber = is_array($details) ? ($details['provider_account_number'] ?? $details['account_number'] ?? null) : null;
        if (! $owner instanceof Model || $assignment->provider !== 'netbank' || ! is_string($accountNumber) || $accountNumber === ''
            || preg_replace('/\D+/', '', $accountNumber) !== ($details['account_number'] ?? null)) {
            throw ValidationException::withMessages(['account' => 'The assigned account is unavailable.']);
        }
        $observation = $this->readiness->handle(0, 'netbank', $accountNumber);
        if (($observation['checked'] ?? false) !== true || ($observation['ready'] ?? false) !== true
            || ($observation['currency'] ?? null) !== 'PHP' || ! is_int($observation['available_balance_minor'] ?? null)) {
            throw ValidationException::withMessages(['account' => 'The bank could not confirm a current available balance for this account.']);
        }
        DB::transaction(function () use ($assignment, $connectionReference, $owner, $actor): void {
            $locked = BankAccountAssignment::query()->lockForUpdate()->findOrFail($assignment->getKey());
            if ($locked->owner_type !== $owner->getMorphClass() || (string) $locked->owner_id !== (string) $owner->getKey()
                || $locked->details_ciphertext !== $assignment->details_ciphertext) {
                throw ValidationException::withMessages(['account' => 'The account changed while it was being checked.']);
            }
            $this->connections->bind($owner, (int) $locked->getKey(), $connectionReference);
            $link = $locked->providerAccountLink()->lockForUpdate()->first();
            if ($link === null || $link->owner_type !== $owner->getMorphClass()
                || (string) $link->owner_id !== (string) $owner->getKey() || $link->provider !== 'netbank'
                || $link->disabled_at !== null) {
                throw ValidationException::withMessages(['account' => 'The provider account link is unavailable.']);
            }
            $metadata = is_array($link->metadata) ? $link->metadata : [];
            $metadata['disbursement_assignment'] = [
                'actor_type' => $actor->getMorphClass(), 'actor_id' => (string) $actor->getKey(), 'checked_at' => now()->toIso8601String(),
            ];
            $link->forceFill([
                'status' => 'ready', 'verification_status' => 'routing_configured', 'ready_at' => now(),
                'capabilities' => array_values(array_unique([...($link->capabilities ?? []), 'disbursement'])),
                'metadata' => $metadata,
            ])->save();
        }, attempts: 3);
    }

    public function enableFunding(BankAccountAssignment $assignment, Model $actor, bool $historyReviewed): void
    {
        if (! $historyReviewed || ! (bool) config('x-change.funding.dedicated_netbank_vca_enabled', false)) {
            throw ValidationException::withMessages(['account' => 'Funding is unavailable until the VCA history has been reviewed and dedicated funding is enabled.']);
        }

        $this->configureFunding($assignment, $actor, 'reviewed_history');
    }

    /** @param list<string> $knownAddresses */
    public function enableFreshFunding(BankAccountAssignment $assignment, Model $actor, array $knownAddresses, bool $soleWriterConfirmed): void
    {
        if (! $this->freshFundingCommissioningAvailable() || ! $soleWriterConfirmed
            || ! (bool) config('x-change.funding.dedicated_netbank_vca_enabled', false)) {
            throw ValidationException::withMessages(['account' => 'Fresh funding commissioning is unavailable.']);
        }

        DB::transaction(function () use ($assignment, $actor, $knownAddresses): void {
            $details = BankAccountAssignment::query()->lockForUpdate()->findOrFail($assignment->getKey())->details_ciphertext;
            $alias = is_array($details) ? ($details['vca_alias'] ?? null) : null;
            if (! is_string($alias) || ! preg_match('/\A[0-9]{5}\z/', $alias)) {
                throw ValidationException::withMessages(['account' => 'The assigned account alias is invalid.']);
            }
            foreach ($knownAddresses as $address) {
                if (! is_string($address) || ! preg_match(ReserveNetbankVca::HistoricalAddressPattern, $address) || ! str_starts_with($address, $alias)) {
                    throw ValidationException::withMessages(['account' => 'A known funding address does not match the account alias.']);
                }
                app(ReserveNetbankVca::class)->recordHistoricalAddress($address);
            }
            $this->configureFunding($assignment, $actor, 'fresh_intents_only');
        }, attempts: 3);
    }

    private function freshFundingCommissioningAvailable(): bool
    {
        return app()->environment(['local', 'testing'])
            || (app()->isProduction() && (bool) config('x-change.funding.dedicated_netbank_production_fresh_commissioning_enabled', false));
    }

    private function configureFunding(BankAccountAssignment $assignment, Model $actor, string $basis): void
    {
        DB::transaction(function () use ($assignment, $actor, $basis): void {
            $locked = BankAccountAssignment::query()->lockForUpdate()->findOrFail($assignment->getKey());
            $owner = $locked->owner;
            $details = $locked->details_ciphertext;
            $number = is_array($details) ? ($details['account_number'] ?? null) : null;
            $alias = is_array($details) ? ($details['vca_alias'] ?? null) : null;
            if (! $owner instanceof Model || $locked->provider !== 'netbank' || ! is_string($number) || ! is_string($alias)) {
                throw ValidationException::withMessages(['account' => 'The assigned account is unavailable.']);
            }
            $preflight = app(NetbankAccountAssignmentPreflight::class);
            if (! $preflight->inspect()['ready']) {
                throw ValidationException::withMessages(['account' => 'Account records need reconciliation.']);
            }
            $preflight->assertAliasAssignable($alias, $number);
            try {
                $this->connections->resolve($owner, (int) $locked->provider_account_link_id);
            } catch (RuntimeException) {
                throw ValidationException::withMessages(['account' => 'A separate Treasury connection is required before funding can be enabled.']);
            }
            $link = $locked->providerAccountLink()->lockForUpdate()->first();
            $routing = $link?->routing_profile_ciphertext;
            if ($link === null || $link->owner_type !== $owner->getMorphClass()
                || (string) $link->owner_id !== (string) $owner->getKey() || $link->provider !== 'netbank'
                || $link->disabled_at !== null || ! is_array($routing)
                || preg_replace('/\D+/', '', (string) ($routing['bank_account_number'] ?? '')) !== $number
                || ($routing['vca_alias'] ?? null) !== $alias) {
                throw ValidationException::withMessages(['account' => 'The account funding details are inconsistent.']);
            }
            $metadata = is_array($link->metadata) ? $link->metadata : [];
            if (in_array('funding', $link->capabilities ?? [], true)
                && (data_get($metadata, 'funding_assignment.historical_references_reviewed') === true
                    || data_get($metadata, 'funding_assignment.scope') === 'fresh_intents_only')) {
                return;
            }
            $metadata['funding_assignment'] = [
                'actor_type' => $actor->getMorphClass(), 'actor_id' => (string) $actor->getKey(), 'checked_at' => now()->toIso8601String(),
                'historical_references_reviewed' => $basis === 'reviewed_history',
                'scope' => $basis,
                ...($basis === 'fresh_intents_only' ? ['fresh_since' => now()->format('Y-m-d\TH:i:s.uP')] : []),
            ];
            $link->forceFill([
                'status' => 'ready', 'verification_status' => 'routing_configured', 'ready_at' => now(),
                'capabilities' => array_values(array_unique([...($link->capabilities ?? []), 'funding'])),
                'metadata' => $metadata,
            ])->save();
            app(AuditLoggerContract::class)->log('funding.assigned_account.enabled', [
                'account_assignment_id' => $locked->getKey(), 'actor_type' => $actor->getMorphClass(),
                'actor_id' => (string) $actor->getKey(), 'treasury_connection' => $locked->treasury_connection_reference,
            ]);
        }, attempts: 3);
    }
}
