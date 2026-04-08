<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\RedemptionCompletionContextContract;
use LBHurtado\XChange\Contracts\RedemptionCompletionStoreContract;
use LBHurtado\XChange\Data\Redemption\LoadRedemptionCompletionContextResultData;

class DefaultRedemptionCompletionContextService implements RedemptionCompletionContextContract
{
    public function __construct(
        protected RedemptionCompletionStoreContract $store,
    ) {}

    public function load(
        Voucher $voucher,
        ?string $referenceId = null,
        ?string $flowId = null,
    ): LoadRedemptionCompletionContextResultData {
        if (! $referenceId && ! $flowId) {
            return new LoadRedemptionCompletionContextResultData(
                voucher_code: (string) $voucher->code,
                can_confirm: false,
                reference_id: null,
                flow_id: null,
                collected_data: [],
                flat_data: [],
                wallet: [],
                inputs: [],
                messages: ['Session expired. Please try again.'],
            );
        }

        $state = $referenceId
            ? $this->store->findByReference($referenceId)
            : $this->store->findByFlowId((string) $flowId);

        if (! $state) {
            return new LoadRedemptionCompletionContextResultData(
                voucher_code: (string) $voucher->code,
                can_confirm: false,
                reference_id: $referenceId,
                flow_id: $flowId,
                collected_data: [],
                flat_data: [],
                wallet: [],
                inputs: [],
                messages: ['Session expired. Please try again.'],
            );
        }

        /** @var array<string, mixed> $collectedData */
        $collectedData = (array) ($state['collected_data'] ?? []);
        $resolvedFlowId = isset($state['flow_id']) ? (string) $state['flow_id'] : $flowId;

        $flatData = $this->mapCollectedData($collectedData);

        $wallet = [
            'amount' => Arr::get($flatData, 'amount'),
            'settlement_rail' => Arr::get($flatData, 'settlement_rail'),
            'mobile' => Arr::get($flatData, 'mobile'),
            'recipient_country' => Arr::get($flatData, 'recipient_country', 'PH'),
            'bank_code' => Arr::get($flatData, 'bank_code'),
            'account_number' => Arr::get($flatData, 'account_number'),
        ];

        $inputs = $this->extractInputs($flatData);

        return new LoadRedemptionCompletionContextResultData(
            voucher_code: (string) $voucher->code,
            can_confirm: true,
            reference_id: $referenceId,
            flow_id: $resolvedFlowId,
            collected_data: $collectedData,
            flat_data: $flatData,
            wallet: $wallet,
            inputs: $inputs,
            messages: [],
        );
    }

    /**
     * @param  array<string, mixed>  $collectedData
     * @return array<string, mixed>
     */
    protected function mapCollectedData(array $collectedData): array
    {
        $flattened = [];

        foreach ($collectedData as $stepData) {
            if (is_array($stepData)) {
                /** @var array<string, mixed> $stepData */
                $flattened = array_merge($flattened, $stepData);
            }
        }

        return $this->applyFieldMappings($flattened);
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed>
     */
    protected function applyFieldMappings(array $inputs): array
    {
        /** @var array<string, string> $mappings */
        $mappings = (array) config('x-change.redemption.field_mappings', [
            'full_name' => 'name',
            'date_of_birth' => 'birth_date',
            'otp_code' => 'otp',
        ]);

        $mapped = $inputs;

        foreach ($mappings as $source => $target) {
            if (array_key_exists($source, $mapped)) {
                $mapped[$target] = $mapped[$source];
                unset($mapped[$source]);
            }
        }

        return $mapped;
    }

    /**
     * @param  array<string, mixed>  $flatData
     * @return array<string, mixed>
     */
    protected function extractInputs(array $flatData): array
    {
        return Collection::make($flatData)
            ->except([
                'mobile',
                'recipient_country',
                'bank_code',
                'account_number',
                'amount',
                'settlement_rail',
            ])
            ->toArray();
    }
}
