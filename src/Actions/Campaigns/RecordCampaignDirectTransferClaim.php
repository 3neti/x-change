<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Campaigns;

use Illuminate\Support\Facades\DB;
use LBHurtado\Voucher\Data\ExecutionResultData;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XCampaign\Models\CampaignWorksheetFulfillment;
use LBHurtado\XChange\Models\VoucherClaim;
use RuntimeException;

final readonly class RecordCampaignDirectTransferClaim
{
    public function start(Voucher $voucher, CampaignWorksheetFulfillment $fulfillment): VoucherClaim
    {
        $row = $fulfillment->row;
        $beneficiary = (array) ($row?->beneficiary_ciphertext ?? []);
        $idempotencyKey = 'campaign-direct-transfer:'.$fulfillment->reference;

        if ($row === null) {
            throw new RuntimeException('Campaign direct transfer claim requires its frozen worksheet row.');
        }

        return DB::transaction(function () use (
            $beneficiary,
            $fulfillment,
            $idempotencyKey,
            $row,
            $voucher,
        ): VoucherClaim {
            Voucher::query()->whereKey($voucher->getKey())->lockForUpdate()->firstOrFail();

            $existing = VoucherClaim::query()
                ->where('voucher_id', $voucher->getKey())
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($existing instanceof VoucherClaim) {
                return $existing;
            }

            return VoucherClaim::query()->create([
                'voucher_id' => $voucher->getKey(),
                'claim_number' => 1,
                'claim_type' => 'withdraw',
                'status' => 'executing',
                'requested_amount_minor' => (int) $row->amount_minor,
                'disbursed_amount_minor' => 0,
                'remaining_balance_minor' => (int) $row->amount_minor,
                'currency' => (string) $row->currency,
                'claimer_mobile' => $this->stringValue($beneficiary['mobile'] ?? null),
                'recipient_country' => 'PH',
                'bank_code' => $this->stringValue($beneficiary['bank_code'] ?? null),
                'account_number_masked' => $this->mask($this->stringValue($beneficiary['bank_account'] ?? null)),
                'idempotency_key' => $idempotencyKey,
                'reference' => (string) $fulfillment->reference,
                'attempted_at' => now(),
                'meta' => [
                    'execution' => [
                        'schema' => 'x-change.campaign-direct-transfer-claim.v1',
                        'authority' => 'maker_checker',
                        'fulfillment_reference' => (string) $fulfillment->reference,
                    ],
                ],
            ]);
        }, attempts: 5);
    }

    public function finish(VoucherClaim $claim, ExecutionResultData $result): void
    {
        DB::transaction(function () use ($claim, $result): void {
            $locked = VoucherClaim::query()->lockForUpdate()->findOrFail($claim->getKey());
            $successful = $result->successful && $result->status === 'succeeded';
            if ($locked->status !== 'executing' && ! ($successful && $locked->status === 'succeeded')) {
                return;
            }

            $meta = (array) $locked->meta;
            data_set($meta, 'execution.status', $result->status);
            data_set($meta, 'execution.failure', $result->failure);

            $locked->forceFill([
                'status' => $successful ? 'succeeded' : 'pending_review',
                'disbursed_amount_minor' => $successful ? $locked->requested_amount_minor : 0,
                'remaining_balance_minor' => $successful ? 0 : $locked->requested_amount_minor,
                'completed_at' => $successful ? now() : null,
                'failure_message' => $successful
                    ? null
                    : 'The provider outcome requires reconciliation before recovery can continue.',
                'meta' => $meta,
            ])->save();
        }, attempts: 5);
    }

    private function stringValue(mixed $value): ?string
    {
        return is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : null;
    }

    private function mask(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return str_repeat('*', max(0, strlen($value) - 4)).substr($value, -4);
    }
}
