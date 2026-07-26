<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Treasury;

use Bavix\Wallet\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryMetadataSanitizerContract;
use LBHurtado\Wallet\Treasury\Models\TreasuryInventoryOperation;
use LBHurtado\Wallet\Treasury\Models\TreasuryPositionOperation;
use LBHurtado\XJournal\Data\ExecutionActorData;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Data\ExecutionReferenceData;
use LBHurtado\XJournal\Data\ExecutionSubjectData;
use LBHurtado\XJournal\Services\ExecutionJournalRecorder;
use RuntimeException;

final readonly class TreasurySensitiveMetadataRedaction
{
    public function __construct(
        private TreasuryMetadataSanitizerContract $sanitizer,
        private ExecutionJournalRecorder $journal,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function inspect(): array
    {
        return $this->result(
            $this->candidates(),
            committed: false,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function redact(string $authorizationReference): array
    {
        $authorizationReference = trim($authorizationReference);

        if (
            $authorizationReference === ''
            || mb_strlen($authorizationReference) > 191
        ) {
            throw new RuntimeException(
                'A stable authorization reference of at most 191 characters is required.',
            );
        }

        $candidates = $this->candidates();

        if ($candidates === []) {
            return $this->result($candidates, committed: true);
        }

        DB::transaction(function () use (
            $candidates,
            $authorizationReference,
        ): void {
            foreach ($candidates as $candidate) {
                DB::table($candidate['table'])
                    ->where('id', $candidate['id'])
                    ->update([
                        $candidate['column'] => json_encode(
                            $candidate['sanitized'],
                            JSON_THROW_ON_ERROR,
                        ),
                    ]);
            }

            $scope = hash('sha256', json_encode(
                array_map(
                    static fn (array $candidate): array => [
                        'type' => $candidate['type'],
                        'id' => $candidate['id'],
                        'reference' => $candidate['reference'],
                    ],
                    $candidates,
                ),
                JSON_THROW_ON_ERROR,
            ));
            $this->journal->record(new ExecutionJournalEntryData(
                eventType: 'treasury.sensitive_metadata.redacted',
                occurredAt: CarbonImmutable::now(),
                actor: new ExecutionActorData(
                    id: null,
                    type: 'system_control',
                ),
                subject: new ExecutionSubjectData(
                    id: 'treasury',
                    type: 'treasury_metadata',
                    display: 'Treasury Metadata',
                ),
                references: new ExecutionReferenceData(
                    correlationId: 'treasury-metadata-redaction:'.$scope,
                    executionId: $scope,
                    metadata: [
                        'authorization_reference' => $authorizationReference,
                    ],
                ),
                idempotencyKey: 'x-change:treasury-metadata-redaction:'
                    .$scope,
                payload: [
                    'status' => 'redacted',
                    'classification' => 'sensitive_metadata_removal',
                    'field_values_persisted' => false,
                    'request_hashes_changed' => false,
                    'position_operation_count' => $this->count(
                        $candidates,
                        'position_operation',
                    ),
                    'inventory_operation_count' => $this->count(
                        $candidates,
                        'inventory_operation',
                    ),
                    'ledger_transaction_count' => $this->count(
                        $candidates,
                        'ledger_transaction',
                    ),
                ],
                metadata: [
                    'schema' => 'x-change.treasury-sensitive-metadata-redaction.v1',
                    'source' => 'guarded_security_redaction',
                ],
            ));
        }, attempts: 5);

        return $this->result($candidates, committed: true);
    }

    /**
     * @return list<array{
     *     type: string,
     *     table: string,
     *     column: string,
     *     id: int,
     *     reference: string,
     *     sanitized: array<string, mixed>
     * }>
     */
    private function candidates(): array
    {
        return [
            ...$this->modelCandidates(
                TreasuryPositionOperation::query()
                    ->orderBy('id')
                    ->get(),
                'position_operation',
                'metadata',
                'operation_reference',
            ),
            ...$this->modelCandidates(
                TreasuryInventoryOperation::query()
                    ->orderBy('id')
                    ->get(),
                'inventory_operation',
                'metadata',
                'operation_reference',
            ),
            ...$this->modelCandidates(
                Transaction::query()->orderBy('id')->get(),
                'ledger_transaction',
                'meta',
                'uuid',
            ),
        ];
    }

    /**
     * @param  iterable<int, Model>  $models
     * @return list<array{
     *     type: string,
     *     table: string,
     *     column: string,
     *     id: int,
     *     reference: string,
     *     sanitized: array<string, mixed>
     * }>
     */
    private function modelCandidates(
        iterable $models,
        string $type,
        string $column,
        string $referenceColumn,
    ): array {
        $candidates = [];

        foreach ($models as $model) {
            $metadata = $model->getAttribute($column);

            if (! is_array($metadata)) {
                continue;
            }

            $sanitized = $this->sanitizer->forPersistence(
                $metadata,
            );

            if ($sanitized === $metadata) {
                continue;
            }

            $candidates[] = [
                'type' => $type,
                'table' => $model->getTable(),
                'column' => $column,
                'id' => (int) $model->getKey(),
                'reference' => (string) $model->getAttribute(
                    $referenceColumn,
                ),
                'sanitized' => $sanitized,
            ];
        }

        return $candidates;
    }

    /**
     * @param  list<array{type: string}>  $candidates
     */
    private function count(
        array $candidates,
        string $type,
    ): int {
        return count(array_filter(
            $candidates,
            static fn (array $candidate): bool => $candidate['type']
                === $type,
        ));
    }

    /**
     * @param  list<array{
     *     type: string,
     *     id: int,
     *     reference: string
     * }>  $candidates
     * @return array<string, mixed>
     */
    private function result(
        array $candidates,
        bool $committed,
    ): array {
        return [
            'schema' => 'x-change.treasury-sensitive-metadata-redaction.v1',
            'success' => true,
            'status' => match (true) {
                $candidates === [] => 'already_sanitized',
                $committed => 'redacted',
                default => 'ready',
            },
            'candidate_count' => count($candidates),
            'position_operation_count' => $this->count(
                $candidates,
                'position_operation',
            ),
            'inventory_operation_count' => $this->count(
                $candidates,
                'inventory_operation',
            ),
            'ledger_transaction_count' => $this->count(
                $candidates,
                'ledger_transaction',
            ),
            'references' => array_values(array_filter(array_map(
                static fn (array $candidate): ?string => in_array(
                    $candidate['type'],
                    ['position_operation', 'inventory_operation'],
                    true,
                )
                    ? $candidate['reference']
                    : null,
                $candidates,
            ))),
            'committed' => $committed,
            'request_hashes_changed' => false,
            'money_changed' => false,
            'provider_calls' => false,
        ];
    }
}
