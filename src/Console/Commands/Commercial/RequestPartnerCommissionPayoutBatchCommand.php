<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Commercial;

use Illuminate\Console\Command;
use LBHurtado\XChange\Actions\Commercial\RequestPartnerCommissionPayoutBatch;
use LBHurtado\XChange\Data\Commercial\PartnerCommissionPayoutBatchRequestData;
use LBHurtado\XChange\Services\Commercial\CommercialOperatorResolver;

final class RequestPartnerCommissionPayoutBatchCommand extends Command
{
    protected $signature = 'x-change:commercial:commission:request
        {operator? : Maker identity}
        {--column=mobile}
        {--reference=}
        {--partner= : Immutable attributed partner reference}
        {--provider=netbank}
        {--connection=netbank-primary}
        {--currency=PHP}
        {--period-start=}
        {--period-end=}
        {--bank= : Bank or wallet code}
        {--account= : Receiving account number}
        {--name= : Recipient name}
        {--mobile= : Contact mobile}
        {--idempotency-key=}
        {--json}';

    protected $description = 'Aggregate earned partner commission and request an independently approved payout.';

    public function handle(
        CommercialOperatorResolver $operators,
        RequestPartnerCommissionPayoutBatch $action,
    ): int {
        $maker = $operators->resolve(
            (string) ($this->argument('operator') ?: $this->ask('Maker identity')),
            (string) $this->option('column'),
        );
        $reference = trim((string) ($this->option('reference') ?: 'commission-payout:'.now()->format('Ymd-His')));
        $batch = $action->execute($maker, new PartnerCommissionPayoutBatchRequestData(
            reference: $reference,
            partnerReference: (string) ($this->option('partner') ?: $this->ask('Partner reference')),
            provider: (string) $this->option('provider'),
            connectionReference: (string) $this->option('connection'),
            currency: (string) $this->option('currency'),
            periodStartedAt: (string) ($this->option('period-start') ?: now()->startOfMonth()->toIso8601String()),
            periodEndedAt: (string) ($this->option('period-end') ?: now()->endOfMonth()->toIso8601String()),
            bankCode: (string) ($this->option('bank') ?: $this->ask('Receiving bank or wallet code')),
            accountNumber: (string) ($this->option('account') ?: $this->secret('Receiving account number')),
            recipientName: (string) ($this->option('name') ?: $this->ask('Recipient name')),
            mobile: (string) ($this->option('mobile') ?: $this->ask('Contact mobile')),
            idempotencyKey: (string) ($this->option('idempotency-key') ?: $reference),
        ));
        $payload = [
            'schema' => 'x-change.partner-commission-payout-batch.v1',
            'reference' => $batch->reference,
            'status' => $batch->status->value,
            'partner_reference' => $batch->partner_reference,
            'amount_minor' => $batch->amount_minor,
            'currency' => $batch->currency,
            'allocation_count' => $batch->lines()->count(),
            'destination' => $batch->destination_summary,
        ];
        $this->line((bool) $this->option('json')
            ? json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)
            : sprintf('%s: %s', $batch->reference, $batch->status->value));

        return self::SUCCESS;
    }
}
