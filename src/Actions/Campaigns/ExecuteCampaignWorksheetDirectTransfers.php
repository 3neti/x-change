<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Campaigns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Data\ExecutionResultData;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Voucher\Services\ExecutionEngine;
use LBHurtado\XCampaign\Models\CampaignWorksheetAuthorization;
use LBHurtado\XCampaign\Models\CampaignWorksheetFulfillment;
use LBHurtado\XChange\Contracts\ExecutionResultHandoffPipelineContract;
use Propaganistas\LaravelPhone\PhoneNumber;
use RuntimeException;

final readonly class ExecuteCampaignWorksheetDirectTransfers
{
    public function __construct(
        private ExecutionEngine $engine,
        private ExecutionResultHandoffPipelineContract $handoffs,
    ) {}

    /** @return array{completed: int, indeterminate: int, skipped: int} */
    public function handle(
        CampaignWorksheetAuthorization $authorization,
        Model $owner,
        int $limit = 100,
    ): array {
        $authorization->loadMissing(['worksheet', 'fulfillments.row']);
        $worksheet = $authorization->worksheet;
        if ($authorization->status !== 'authorized'
            || $worksheet === null
            || $worksheet->fulfillment_mode !== 'direct_bank_transfer'
            || data_get($worksheet->metadata, 'lifecycle.automatic_fulfillment') !== true) {
            throw new RuntimeException('The campaign is not authorized for lifecycle direct transfers.');
        }
        if ($worksheet->owner_type !== $owner->getMorphClass()
            || (string) $worksheet->owner_id !== (string) $owner->getKey()) {
            throw new RuntimeException('Campaign direct transfers must be executed by the worksheet owner authority.');
        }

        $summary = ['completed' => 0, 'indeterminate' => 0, 'skipped' => 0];
        $fulfillments = $authorization->fulfillments
            ->where('mode', 'direct_bank_transfer')
            ->take(max(1, min($limit, 500)));

        foreach ($fulfillments as $fulfillment) {
            if ($fulfillment->status === 'completed') {
                $summary['skipped']++;

                continue;
            }
            if ($fulfillment->status !== 'issued' || $fulfillment->pay_code === null) {
                $summary['indeterminate']++;

                continue;
            }

            $locked = DB::transaction(function () use ($fulfillment): CampaignWorksheetFulfillment {
                $locked = CampaignWorksheetFulfillment::query()
                    ->with('row')
                    ->lockForUpdate()
                    ->findOrFail($fulfillment->getKey());
                if ($locked->status !== 'issued') {
                    return $locked;
                }

                $locked->forceFill([
                    'status' => 'executing',
                    'metadata' => array_replace($locked->metadata ?? [], [
                        'execution_schema' => 'x-change.campaign-direct-transfer.v1',
                        'execution_started_at' => now()->toIso8601String(),
                    ]),
                ])->save();

                return $locked;
            }, attempts: 5);

            if ($locked->status !== 'executing') {
                $summary['skipped']++;

                continue;
            }

            $result = $this->execute($authorization, $locked);
            $this->handoffs->process($result['result'], $result['context']);

            DB::transaction(function () use ($locked, $result, &$summary): void {
                $current = CampaignWorksheetFulfillment::query()->lockForUpdate()->findOrFail($locked->getKey());
                if ($current->status !== 'executing') {
                    $summary['skipped']++;

                    return;
                }

                $successful = $result['result']->successful && $result['result']->status === 'succeeded';
                $current->forceFill([
                    'status' => $successful ? 'completed' : 'provider_indeterminate',
                    'provider_transfer_reference' => $this->providerReference($result['result']),
                    'metadata' => array_replace($current->metadata ?? [], [
                        'execution_status' => $result['result']->status,
                        'execution_failure' => $result['result']->failure,
                        'execution_exception' => data_get($result['result']->metadata, 'exception'),
                        'execution_finished_at' => now()->toIso8601String(),
                    ]),
                ])->save();
                $summary[$successful ? 'completed' : 'indeterminate']++;
            }, attempts: 5);
        }

        return $summary;
    }

    /** @return array{result: ExecutionResultData, context: ExecutionContextData} */
    private function execute(
        CampaignWorksheetAuthorization $authorization,
        CampaignWorksheetFulfillment $fulfillment,
    ): array {
        $row = $fulfillment->row;
        $beneficiary = (array) ($row?->beneficiary_ciphertext ?? []);
        $mobile = $this->requiredString($beneficiary, 'mobile');
        $voucher = Voucher::query()->where('code', $fulfillment->pay_code)->firstOrFail();
        $context = ExecutionContextData::fromRedemption(
            voucher: $voucher,
            contact: Contact::fromPhoneNumber(new PhoneNumber($mobile, 'PH')),
            voucherCode: (string) $voucher->code,
            meta: [
                'operation' => 'claim_transfer',
                'claim' => [
                    'mobile' => $mobile,
                    'recipient_country' => 'PH',
                    'bank_account' => [
                        'bank_code' => $this->requiredString($beneficiary, 'bank_code'),
                        'account_number' => $this->requiredString($beneficiary, 'bank_account'),
                    ],
                    'inputs' => [],
                    '_meta' => [
                        'idempotency_key' => 'campaign-direct-transfer:'.$fulfillment->reference,
                    ],
                ],
                'poll' => [
                    'timeout' => 180,
                    'poll' => 10,
                    'accept_pending' => false,
                ],
            ],
            correlation: [
                'scenario' => 'campaign_batch',
                'authorization_reference' => (string) $authorization->reference,
                'fulfillment_reference' => (string) $fulfillment->reference,
                'idempotency_key' => 'campaign-direct-transfer:'.$fulfillment->reference,
            ],
        );

        return ['result' => $this->engine->execute($context), 'context' => $context];
    }

    /** @param array<string, mixed> $beneficiary */
    private function requiredString(array $beneficiary, string $key): string
    {
        $value = $beneficiary[$key] ?? null;
        if (! is_scalar($value) || trim((string) $value) === '') {
            throw new RuntimeException(sprintf('Campaign direct transfer beneficiary is missing %s.', $key));
        }

        return trim((string) $value);
    }

    private function providerReference(ExecutionResultData $result): ?string
    {
        foreach ($result->providerReferences as $reference) {
            $value = $reference['value'] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        $value = data_get($result->metadata, 'provider_transaction_id');

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
