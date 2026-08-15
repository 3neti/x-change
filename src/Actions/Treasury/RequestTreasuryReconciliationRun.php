<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Treasury;

use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LBHurtado\XChange\Enums\TreasuryOperatorCapability;
use LBHurtado\XChange\Enums\TreasuryReconciliationRunStatus;
use LBHurtado\XChange\Models\TreasuryReconciliationRun;
use LBHurtado\XChange\Services\Treasury\TreasuryOperatorAuthority;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;
use LBHurtado\XChange\Services\Treasury\TreasuryReconciliationRunJournal;

final readonly class RequestTreasuryReconciliationRun
{
    public function __construct(
        private TreasuryOperatorAuthority $authority,
        private TreasuryProviderConnectionCatalog $connections,
        private TreasuryReconciliationRunJournal $journal,
    ) {}

    public function handle(
        string $connectionReference,
        string $purpose,
        string $idempotencyReference,
        Model $maker,
    ): TreasuryReconciliationRun {
        $this->authority->assertAllows($maker, TreasuryOperatorCapability::RequestReconciliation);
        $connectionReference = trim($connectionReference);
        $purpose = trim($purpose);
        $idempotencyReference = trim($idempotencyReference);

        if ($connectionReference === '' || $purpose === '' || $idempotencyReference === '') {
            throw new DomainException('The provider reconciliation request is incomplete.');
        }

        $connection = $this->connections->active([$connectionReference])[0];
        $facts = [
            'connection_reference' => $connection->reference,
            'provider' => $connection->provider,
            'currency' => $connection->currency,
            'purpose' => $purpose,
        ];
        $requestHash = hash('sha256', json_encode($facts, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $idempotencyHash = hash('sha256', $idempotencyReference);

        return DB::transaction(function () use ($facts, $idempotencyHash, $maker, $requestHash): TreasuryReconciliationRun {
            $existing = TreasuryReconciliationRun::query()
                ->where('idempotency_reference_hash', $idempotencyHash)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof TreasuryReconciliationRun) {
                if (! hash_equals($existing->request_hash, $requestHash)) {
                    throw new DomainException('The reconciliation idempotency reference is already bound to different input.');
                }

                return $existing;
            }

            $run = TreasuryReconciliationRun::query()->create([
                'reference' => (string) Str::ulid(),
                'status' => TreasuryReconciliationRunStatus::AwaitingApproval,
                ...$facts,
                'request_hash' => $requestHash,
                'idempotency_reference_hash' => $idempotencyHash,
                'maker_type' => $maker->getMorphClass(),
                'maker_id' => (string) $maker->getKey(),
                'submitted_at' => now(),
            ]);
            $this->journal->record($run, 'treasury.reconciliation.requested', $maker);

            return $run;
        }, attempts: 3);
    }
}
