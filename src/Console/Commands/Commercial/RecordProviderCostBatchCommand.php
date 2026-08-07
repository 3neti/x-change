<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Commercial;

use Illuminate\Console\Command;
use LBHurtado\XChange\Actions\Commercial\RecordProviderCostBatch;
use LBHurtado\XChange\Data\Commercial\ProviderCostBatchEvidenceData;
use LBHurtado\XChange\Services\Commercial\CommercialOperatorResolver;

final class RecordProviderCostBatchCommand extends Command
{
    protected $signature = 'x-change:commercial:provider-cost:record
        {operator? : Operator identity}
        {--column=mobile : Auth model identity column}
        {--reference= : Stable batch reference}
        {--provider=netbank}
        {--connection=netbank-primary}
        {--currency=PHP}
        {--evidence-type=provider_statement}
        {--evidence-reference= : Provider statement, invoice, or debit reference}
        {--amount= : Observed amount in major units}
        {--period-start= : Inclusive ISO-8601 period start}
        {--period-end= : Inclusive ISO-8601 period end}
        {--observed-at= : Evidence observation timestamp}
        {--idempotency-key= : Stable replay key}
        {--json}';

    protected $description = 'Match authoritative provider-cost evidence to earned provider-cost allocations.';

    public function handle(
        CommercialOperatorResolver $operators,
        RecordProviderCostBatch $action,
    ): int {
        $operator = $operators->resolve(
            (string) ($this->argument('operator') ?: $this->ask('Operator identity')),
            (string) $this->option('column'),
        );
        $reference = trim((string) ($this->option('reference') ?: 'provider-cost:'.now()->format('Ymd-His')));
        $evidenceReference = trim((string) ($this->option('evidence-reference')
            ?: $this->ask('Authoritative evidence reference')));
        $amount = (string) ($this->option('amount') ?: $this->ask('Observed provider cost (PHP)'));
        $periodStart = (string) ($this->option('period-start') ?: now()->startOfMonth()->toIso8601String());
        $periodEnd = (string) ($this->option('period-end') ?: now()->endOfMonth()->toIso8601String());
        $batch = $action->execute($operator, new ProviderCostBatchEvidenceData(
            reference: $reference,
            provider: (string) $this->option('provider'),
            connectionReference: (string) $this->option('connection'),
            currency: (string) $this->option('currency'),
            evidenceType: (string) $this->option('evidence-type'),
            evidenceReference: $evidenceReference,
            observedAmountMinor: (int) round((float) $amount * 100),
            periodStartedAt: $periodStart,
            periodEndedAt: $periodEnd,
            observedAt: (string) ($this->option('observed-at') ?: now()->toIso8601String()),
            idempotencyKey: (string) ($this->option('idempotency-key') ?: $reference),
        ));
        $payload = [
            'schema' => 'x-change.commercial-provider-cost-batch.v1',
            'reference' => $batch->reference,
            'status' => $batch->status->value,
            'expected_amount_minor' => $batch->expected_amount_minor,
            'observed_amount_minor' => $batch->observed_amount_minor,
            'variance_amount_minor' => $batch->variance_amount_minor,
            'settlement_count' => $batch->lines()->count(),
        ];

        $this->line((bool) $this->option('json')
            ? json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)
            : sprintf('%s: %s', $batch->reference, $batch->status->value));

        return $batch->status->value === 'settled' ? self::SUCCESS : self::FAILURE;
    }
}
