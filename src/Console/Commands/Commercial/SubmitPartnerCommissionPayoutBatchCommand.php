<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Commercial;

use Illuminate\Console\Command;
use LBHurtado\XChange\Actions\Commercial\SubmitPartnerCommissionPayoutBatch;
use LBHurtado\XChange\Models\PartnerCommissionPayoutBatch;
use LBHurtado\XChange\Services\Commercial\CommercialOperatorResolver;

final class SubmitPartnerCommissionPayoutBatchCommand extends Command
{
    protected $signature = 'x-change:commercial:commission:submit
        {reference : Approved payout batch reference}
        {operator? : Execution operator identity}
        {--column=mobile}
        {--idempotency-key=}
        {--confirm-live : Confirm that this command can move real money}
        {--json}';

    protected $description = 'Submit one approved commission payout through the configured EMI provider.';

    public function handle(
        CommercialOperatorResolver $operators,
        SubmitPartnerCommissionPayoutBatch $action,
    ): int {
        if (! (bool) $this->option('confirm-live')) {
            $this->error('Commission payout submission requires --confirm-live.');

            return self::FAILURE;
        }

        $operator = $operators->resolve(
            (string) ($this->argument('operator') ?: $this->ask('Execution operator identity')),
            (string) $this->option('column'),
        );
        $batch = PartnerCommissionPayoutBatch::query()
            ->where('reference', $this->argument('reference'))
            ->sole();
        $batch = $action->execute(
            $operator,
            $batch,
            (string) ($this->option('idempotency-key') ?: 'submission:'.$batch->reference),
        );
        $payload = [
            'reference' => $batch->reference,
            'status' => $batch->status->value,
            'provider_transaction_recorded' => $batch->provider_transaction_id !== null,
            'treasury_settled' => $batch->settled_at !== null,
        ];
        $this->line((bool) $this->option('json')
            ? json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)
            : sprintf('%s: %s', $batch->reference, $batch->status->value));

        return in_array($batch->status->value, ['pending', 'settled'], true)
            ? self::SUCCESS
            : self::FAILURE;
    }
}
