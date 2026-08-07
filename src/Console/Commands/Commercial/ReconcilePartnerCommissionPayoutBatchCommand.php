<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Commercial;

use Illuminate\Console\Command;
use LBHurtado\XChange\Actions\Commercial\ReconcilePartnerCommissionPayoutBatch;
use LBHurtado\XChange\Models\PartnerCommissionPayoutBatch;
use LBHurtado\XChange\Services\Commercial\CommercialOperatorResolver;

final class ReconcilePartnerCommissionPayoutBatchCommand extends Command
{
    protected $signature = 'x-change:commercial:commission:reconcile
        {reference : Submitted payout batch reference}
        {operator? : Execution operator identity}
        {--column=mobile}
        {--json}';

    protected $description = 'Query authoritative provider status and settle a completed commission payout.';

    public function handle(
        CommercialOperatorResolver $operators,
        ReconcilePartnerCommissionPayoutBatch $action,
    ): int {
        $operator = $operators->resolve(
            (string) ($this->argument('operator') ?: $this->ask('Execution operator identity')),
            (string) $this->option('column'),
        );
        $batch = PartnerCommissionPayoutBatch::query()
            ->where('reference', $this->argument('reference'))
            ->sole();
        $batch = $action->execute($operator, $batch);
        $payload = [
            'reference' => $batch->reference,
            'status' => $batch->status->value,
            'treasury_settled' => $batch->settled_at !== null,
        ];
        $this->line((bool) $this->option('json')
            ? json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)
            : sprintf('%s: %s', $batch->reference, $batch->status->value));

        return $batch->status->value === 'settled' ? self::SUCCESS : self::FAILURE;
    }
}
