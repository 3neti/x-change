<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Claim;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Facades\Log;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Models\ClaimValidationDecision;
use LBHurtado\XJournal\Data\ExecutionActorData;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Data\ExecutionReferenceData;
use LBHurtado\XJournal\Data\ExecutionSubjectData;
use LBHurtado\XJournal\Services\ExecutionJournalRecorder;
use Throwable;

final readonly class ClaimValidationDecisionJournal
{
    public function __construct(private ExecutionJournalRecorder $recorder) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<array{field: string, code: string, severity: string}>  $issues
     */
    public function record(Voucher $voucher, array $payload, string $status, array $issues): ClaimValidationDecision
    {
        $instructions = $voucher->getRawOriginal('instructions')
            ?: data_get($voucher->metadata, 'instructions', []);
        if (is_string($instructions)) {
            $instructions = json_decode($instructions, true);
        } elseif ($instructions instanceof Arrayable) {
            $instructions = $instructions->toArray();
        }
        if (! is_array($instructions)) {
            $instructions = [];
        }
        $instructionFingerprint = hash('sha256', json_encode($instructions, JSON_THROW_ON_ERROR));
        $inputFingerprint = hash('sha256', json_encode([
            'inputs' => data_get($payload, 'inputs', []),
            'mobile' => data_get($payload, 'mobile'),
            'amount' => data_get($payload, 'amount'),
            'destination' => data_get($payload, 'destination'),
        ], JSON_THROW_ON_ERROR));
        $attemptKey = (string) data_get($payload, '_meta.idempotency_key', '');
        $decisionKey = hash('sha256', implode('|', [
            (string) $voucher->getKey(),
            $attemptKey,
            $instructionFingerprint,
            $inputFingerprint,
            $status,
            hash('sha256', json_encode($issues, JSON_THROW_ON_ERROR)),
        ]));

        $decision = ClaimValidationDecision::query()->firstOrCreate(
            ['decision_key' => $decisionKey],
            [
                'voucher_id' => $voucher->getKey(),
                'instruction_fingerprint' => $instructionFingerprint,
                'input_fingerprint' => $inputFingerprint,
                'status' => $status,
                'issues' => $issues,
                'journal_status' => 'pending',
                'occurred_at' => now(),
            ],
        );

        $this->deliver($decision);

        return $decision;
    }

    public function deliverPending(int $limit = 100): int
    {
        $decisions = ClaimValidationDecision::query()
            ->where('journal_status', 'pending')
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->limit(max(1, $limit))
            ->get();

        $decisions->each(fn (ClaimValidationDecision $decision) => $this->deliver($decision));

        return $decisions->count();
    }

    public function deliver(ClaimValidationDecision $decision): void
    {
        if ($decision->journal_status === 'delivered') {
            return;
        }

        try {
            $this->recorder->record(new ExecutionJournalEntryData(
                eventType: 'claim.validation.'.$decision->status,
                occurredAt: CarbonImmutable::parse($decision->occurred_at),
                actor: new ExecutionActorData(id: 'claim-validation-engine', type: 'system'),
                subject: new ExecutionSubjectData(
                    id: (string) $decision->voucher_id,
                    type: 'voucher',
                    display: 'Pay Code',
                ),
                references: new ExecutionReferenceData(
                    correlationId: 'claim-validation:'.$decision->decision_key,
                    executionId: (string) $decision->getKey(),
                ),
                idempotencyKey: 'x-change:claim-validation:'.$decision->decision_key,
                payload: [
                    'decision' => $decision->status,
                    'issues' => $decision->issues,
                    'instruction_fingerprint' => $decision->instruction_fingerprint,
                    'input_fingerprint' => $decision->input_fingerprint,
                ],
                metadata: [
                    'schema' => 'x-change.claim-validation.v1',
                    'source' => 'x_change_claim_validation_decisions',
                ],
            ));

            $decision->forceFill([
                'journal_status' => 'delivered',
                'journal_attempts' => $decision->journal_attempts + 1,
                'journaled_at' => now(),
                'journal_error' => null,
            ])->save();
        } catch (Throwable $exception) {
            Log::warning('Claim validation journal delivery failed', [
                'decision_id' => $decision->getKey(),
                'exception' => $exception::class,
            ]);

            $decision->forceFill([
                'journal_attempts' => $decision->journal_attempts + 1,
                'journal_error' => mb_substr($exception::class, 0, 255),
            ])->save();
        }
    }
}
