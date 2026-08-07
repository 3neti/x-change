<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Commercial;

use Illuminate\Console\Command;
use LBHurtado\XChange\Actions\Commercial\ApprovePartnerCommissionPayoutBatch;
use LBHurtado\XChange\Models\PartnerCommissionPayoutBatch;
use LBHurtado\XChange\Services\Commercial\CommercialOperatorResolver;

final class ApprovePartnerCommissionPayoutBatchCommand extends Command
{
    protected $signature = 'x-change:commercial:commission:approve
        {reference : Payout batch reference}
        {operator? : Checker identity}
        {--column=mobile}
        {--approval-reference= : Independent approval evidence}
        {--json}';

    protected $description = 'Approve an aggregated partner commission payout as an independent checker.';

    public function handle(
        CommercialOperatorResolver $operators,
        ApprovePartnerCommissionPayoutBatch $action,
    ): int {
        $checker = $operators->resolve(
            (string) ($this->argument('operator') ?: $this->ask('Checker identity')),
            (string) $this->option('column'),
        );
        $batch = PartnerCommissionPayoutBatch::query()
            ->where('reference', $this->argument('reference'))
            ->sole();
        $batch = $action->execute(
            $checker,
            $batch,
            (string) ($this->option('approval-reference') ?: $this->ask('Approval reference')),
        );

        $payload = ['reference' => $batch->reference, 'status' => $batch->status->value];
        $this->line((bool) $this->option('json')
            ? json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)
            : sprintf('%s: %s', $batch->reference, $batch->status->value));

        return self::SUCCESS;
    }
}
