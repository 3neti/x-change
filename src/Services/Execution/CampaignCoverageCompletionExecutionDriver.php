<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Execution;

use LBHurtado\Voucher\Contracts\ExecutionDriverContract;
use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Data\ExecutionResultData;
use LBHurtado\Voucher\Services\DefaultExecutionDriver;
use LBHurtado\XChange\Models\CompletionPayCodeIssuance;
use LBHurtado\XChange\Services\Settlement\CampaignWalletPayerMobile;
use LBHurtado\XChange\Support\Auth\MobileNumber;

final readonly class CampaignCoverageCompletionExecutionDriver implements ExecutionDriverContract
{
    public const string Key = 'campaign_coverage_completion';

    public const string PostRedemptionMode = 'execution_only';

    public function __construct(private DefaultExecutionDriver $defaultDriver) {}

    public function key(): string
    {
        return self::Key;
    }

    public function execute(ExecutionContextData $context): ExecutionResultData
    {
        if ($context->voucher === null) {
            return ExecutionResultData::failed($this->key(), 'invalid_completion_voucher');
        }

        $completion = (array) data_get($context->instruction?->metadata, 'completion', []);
        $issuance = CompletionPayCodeIssuance::query()
            ->with(['coverage', 'envelope'])
            ->where('voucher_id', $context->voucher->getKey())
            ->first();

        if (! $issuance instanceof CompletionPayCodeIssuance
            || data_get($completion, 'schema') !== 'x-change.campaign-coverage-completion.v1'
            || data_get($completion, 'coverage_reference') !== $issuance->coverage->reference
            || data_get($completion, 'envelope_reference') !== $issuance->envelope->reference_code
            || data_get($completion, 'driver_id') !== $issuance->driver_id
            || data_get($completion, 'driver_version') !== $issuance->driver_version) {
            return ExecutionResultData::failed($this->key(), 'invalid_completion_voucher');
        }

        $payerMobile = (new CampaignWalletPayerMobile)->resolve($issuance->coverage->recognition);
        if ($payerMobile !== null && MobileNumber::normalize($context->contact?->mobile) !== $payerMobile) {
            return ExecutionResultData::failed($this->key(), 'completion_mobile_mismatch');
        }

        $result = $this->defaultDriver->execute($context);

        if (! $result->successful) {
            return ExecutionResultData::failed($this->key(), $result->failure ?? 'voucher_redemption_rejected');
        }

        return new ExecutionResultData(
            execution_id: $result->execution_id,
            successful: true,
            status: 'succeeded',
            driver: $this->key(),
            events: ['campaign_coverage.completion_claim_redeemed'],
            metadata: [
                'voucher_id' => $context->voucher->getKey(),
                'coverage_reference' => $issuance->coverage->reference,
                'envelope_reference' => $issuance->envelope->reference_code,
            ],
        );
    }
}
