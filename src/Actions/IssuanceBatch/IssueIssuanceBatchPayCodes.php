<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\IssuanceBatch;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\XChange\Actions\PayCode\GeneratePayCode;
use LBHurtado\XChange\Enums\IssuanceBatchStatus;
use LBHurtado\XChange\Models\IssuanceBatch;

final readonly class IssueIssuanceBatchPayCodes
{
    public function __construct(private GeneratePayCode $generate) {}

    public function handle(IssuanceBatch $batch, Model $issuer, string $fundingReference): void
    {
        $batch->forceFill(['status' => IssuanceBatchStatus::Issuing])->save();
        foreach ($batch->items()->where('status', 'pending')->lockForUpdate()->get() as $item) {
            try {
                $payload = [
                    ...VoucherInstructionsData::generateFromScratch()->toArray(),
                    ...(array) $item->payload,
                ];
                $payload['cash'] = ['amount' => $item->amount_minor / 100, 'currency' => 'PHP'];
                if (! is_array($payload['inputs'] ?? null)) {
                    $payload['inputs'] = ['fields' => ['name', 'mobile']];
                }
                if (! is_array($payload['feedback'] ?? null)) {
                    $payload['feedback'] = ['mobile' => data_get($payload, 'mobile'), 'email' => null, 'webhook' => null];
                }
                if (! is_array($payload['rider'] ?? null)) {
                    $payload['rider'] = ['message' => null, 'url' => null];
                }
                $payload['voucher_type'] = 'redeemable';
                $payload['metadata'] = [...(array) ($payload['metadata'] ?? []), 'flow_type' => 'disbursable'];
                $payload['_meta'] = [
                    'source' => 'public.auto-generate.batch',
                    'issuance_batch_reference' => $batch->reference,
                    'on_demand_funding_order' => $fundingReference,
                ];
                $result = $this->generate->handle($payload);
                $item->forceFill([
                    'status' => 'issued',
                    'pay_code' => $result->code,
                    'voucher_id' => (string) $result->voucher_id,
                    'claim_url' => $result->links->redeem,
                    'issued_at' => now(),
                ])->save();
            } catch (\Throwable $exception) {
                $item->forceFill(['status' => 'failed', 'failure_message' => $exception->getMessage()])->save();
            }
        }
        $remaining = $batch->items()->whereIn('status', ['pending', 'failed'])->exists();
        $batch->forceFill([
            'status' => $remaining ? IssuanceBatchStatus::PartiallyIssued : IssuanceBatchStatus::Issued,
            'issued_at' => $remaining ? null : now(),
        ])->save();
    }
}
