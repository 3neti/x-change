<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Treasury;

use DomainException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LBHurtado\XChange\Enums\TreasuryAccountGrantStatus;
use LBHurtado\XChange\Enums\TreasuryOperatorCapability;
use LBHurtado\XChange\Models\TreasuryAccountGrant;
use LBHurtado\XChange\Services\Treasury\TreasuryAccountGrantJournal;
use LBHurtado\XChange\Services\Treasury\TreasuryOperatorAuthority;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;

final readonly class RequestTreasuryAccountGrant
{
    public function __construct(
        private Application $app,
        private TreasuryOperatorAuthority $authority,
        private TreasuryAccountGrantJournal $journal,
        private TreasuryProviderConnectionCatalog $connections,
    ) {}

    public function handle(
        Model $recipient,
        int $amountMinor,
        string $currency,
        string $connectionReference,
        string $purpose,
        string $idempotencyReference,
        Model $maker,
        bool $testAllocation = false,
    ): TreasuryAccountGrant {
        $this->authority->assertAllows($maker, TreasuryOperatorCapability::RequestAccountGrants);
        $currency = mb_strtoupper(trim($currency));
        $purpose = trim($purpose);
        $connectionReference = trim($connectionReference);
        $idempotencyReference = trim($idempotencyReference);

        if ($amountMinor <= 0 || $currency === '' || $connectionReference === '' || $purpose === '' || $idempotencyReference === '') {
            throw new DomainException('The Treasury Account Grant request is incomplete.');
        }

        $connection = collect($this->connections->active([$connectionReference]))->first();

        if ($connection === null || $connection->currency !== $currency) {
            throw new DomainException('The Treasury Account Grant currency does not match its active connection.');
        }

        if ($testAllocation) {
            $this->assertTestAllocationAllowed($recipient, $amountMinor);
        }

        $facts = [
            'recipient_type' => $recipient->getMorphClass(),
            'recipient_id' => (string) $recipient->getKey(),
            'amount_minor' => $amountMinor,
            'currency' => $currency,
            'connection_reference' => $connectionReference,
            'purpose' => $purpose,
            'test_allocation' => $testAllocation,
        ];
        $requestHash = hash('sha256', json_encode($facts, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $idempotencyHash = hash('sha256', $idempotencyReference);

        return DB::transaction(function () use ($facts, $requestHash, $idempotencyHash, $maker): TreasuryAccountGrant {
            $existing = TreasuryAccountGrant::query()
                ->where('idempotency_reference_hash', $idempotencyHash)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof TreasuryAccountGrant) {
                if (! hash_equals($existing->request_hash, $requestHash)) {
                    throw new DomainException('The Account Grant idempotency reference was reused with different input.');
                }

                return $existing;
            }

            $grant = TreasuryAccountGrant::query()->create([
                'reference' => (string) Str::ulid(),
                'status' => TreasuryAccountGrantStatus::AwaitingApproval,
                ...$facts,
                'request_hash' => $requestHash,
                'idempotency_reference_hash' => $idempotencyHash,
                'maker_type' => $maker->getMorphClass(),
                'maker_id' => (string) $maker->getKey(),
                'submitted_at' => now(),
            ]);
            $this->journal->record($grant, 'treasury.account_grant.requested', $maker);

            return $grant;
        }, attempts: 3);
    }

    private function assertTestAllocationAllowed(Model $recipient, int $amountMinor): void
    {
        if ($this->app->environment('production')
            || ! (bool) config('x-change.treasury_account_grants.test_allocations_enabled', false)) {
            throw new DomainException('Test Account Grants are unavailable in this environment.');
        }

        if ($amountMinor > (int) config('x-change.treasury_account_grants.test_max_amount_minor', 100_000)) {
            throw new DomainException('The Test Account Grant exceeds the per-grant limit.');
        }

        $usedToday = TreasuryAccountGrant::query()
            ->where('recipient_type', $recipient->getMorphClass())
            ->where('recipient_id', $recipient->getKey())
            ->where('test_allocation', true)
            ->whereDate('created_at', today())
            ->sum('amount_minor');

        if ($usedToday + $amountMinor > (int) config('x-change.treasury_account_grants.test_daily_limit_minor', 500_000)) {
            throw new DomainException('The Account has reached its daily Test Funds limit.');
        }
    }
}
