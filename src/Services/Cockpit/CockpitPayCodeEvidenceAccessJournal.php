<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Str;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XJournal\Data\ExecutionActorData;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Data\ExecutionReferenceData;
use LBHurtado\XJournal\Data\ExecutionSubjectData;
use LBHurtado\XJournal\Services\ExecutionJournalRecorder;

final readonly class CockpitPayCodeEvidenceAccessJournal
{
    public function __construct(
        private ExecutionJournalRecorder $journal,
    ) {}

    public function record(
        Voucher $voucher,
        Authenticatable $actor,
        string $source,
        int $evidenceId,
        string $evidenceType,
    ): void {
        $accessReference = (string) Str::uuid();

        $this->journal->record(new ExecutionJournalEntryData(
            eventType: 'pay_code.evidence.viewed',
            occurredAt: CarbonImmutable::now(),
            actor: new ExecutionActorData(
                id: (string) $actor->getAuthIdentifier(),
                type: $actor::class,
            ),
            subject: new ExecutionSubjectData(
                id: (string) $voucher->getKey(),
                type: 'voucher',
                display: 'Pay Code '.$voucher->code,
            ),
            references: new ExecutionReferenceData(
                correlationId: 'pay-code-evidence:'.$voucher->code,
                executionId: $accessReference,
                metadata: [
                    'source' => $source,
                    'evidence_id' => (string) $evidenceId,
                ],
            ),
            idempotencyKey: 'x-change:pay-code-evidence-viewed:'.$accessReference,
            payload: [
                'source' => $source,
                'evidence_type' => $evidenceType,
                'binary_payload_persisted' => false,
                'read_only' => true,
            ],
            metadata: [
                'schema' => 'x-change.pay-code-evidence-access.v1',
                'sensitive_access' => true,
                'provider_calls' => false,
                'moves_money' => false,
            ],
        ));
    }
}
