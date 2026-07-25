<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Execution;

use LBHurtado\Voucher\Contracts\ExecutionDriverContract;
use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Data\ExecutionResultData;
use LBHurtado\Voucher\Services\DefaultExecutionDriver;
use LBHurtado\XChange\Actions\Redemption\SubmitPayCodeClaim;
use LBHurtado\XChange\Contracts\ExecutionCashDisbursementPollerContract;
use LBHurtado\XChange\Data\Redemption\SubmitPayCodeClaimResultData;
use Throwable;

final class XChangeLiveCashExecutionDriver implements ExecutionDriverContract
{
    public function __construct(
        private readonly DefaultExecutionDriver $defaultDriver,
        private readonly ExecutionCashDisbursementPollerContract $poller,
        private readonly SubmitPayCodeClaim $submitPayCodeClaim,
    ) {}

    public function key(): string
    {
        return 'x_change_live_cash';
    }

    public function execute(ExecutionContextData $context): ExecutionResultData
    {
        $events = ['x_change_live_cash.redemption_requested'];
        $normalized = $this->normalizeContext($context);

        try {
            $result = data_get($context->meta, 'claim.amount') === null
                ? $this->defaultDriver->execute($normalized)
                : $this->executeAmountClaim($context);
        } catch (Throwable $exception) {
            return ExecutionResultData::failed(
                driver: $this->key(),
                failure: 'redemption_exception',
                metadata: [
                    'voucher_code' => $context->voucherCode,
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ],
            );
        }

        if (! $result->successful) {
            return new ExecutionResultData(
                execution_id: null,
                successful: false,
                status: 'failed',
                driver: $this->key(),
                events: [...$events, 'x_change_live_cash.redemption_rejected'],
                failure: $result->failure,
                metadata: [
                    'voucher_code' => $context->voucherCode,
                    'compatibility_result' => $result->toArray(),
                ],
            );
        }

        $events[] = 'x_change_live_cash.redemption_succeeded';

        $disbursement = $this->poller->poll(
            code: $context->voucherCode,
            options: (array) data_get($context->meta, 'poll', []),
        );

        $events[] = 'x_change_live_cash.disbursement_polled';

        $status = (string) ($disbursement['current_status'] ?? 'unknown');
        $successful = $status === 'succeeded';

        return new ExecutionResultData(
            execution_id: null,
            successful: $successful,
            status: $successful ? 'succeeded' : $status,
            driver: $this->key(),
            events: $events,
            failure: $successful ? null : 'disbursement_'.$status,
            providerReferences: $this->providerReferences($disbursement),
            reconciliation: $disbursement,
            metadata: [
                'voucher_code' => $context->voucherCode,
                'provider' => $disbursement['provider'] ?? null,
                'provider_transaction_id' => $disbursement['provider_transaction_id'] ?? null,
                'reference_number' => $disbursement['reference_number'] ?? null,
                'settlement_rail' => $disbursement['settlement_rail'] ?? null,
                'destination_account' => $disbursement['destination_account'] ?? null,
            ],
        );
    }

    private function executeAmountClaim(
        ExecutionContextData $context,
    ): ExecutionResultData {
        if ($context->voucher === null) {
            return ExecutionResultData::failed(
                driver: $this->key(),
                failure: 'missing_voucher',
            );
        }

        $result = $this->submitPayCodeClaim->handle(
            $context->voucher,
            (array) data_get($context->meta, 'claim', []),
        );

        if (! $result instanceof SubmitPayCodeClaimResultData) {
            return ExecutionResultData::failed(
                driver: $this->key(),
                failure: 'claim_approval_required',
            );
        }

        if (! $result->claimed || ! in_array(
            $result->status,
            ['succeeded', 'withdrawn'],
            true,
        )) {
            return ExecutionResultData::failed(
                driver: $this->key(),
                failure: 'claim_'.$result->status,
                metadata: $result->toArray(),
            );
        }

        return ExecutionResultData::succeeded(
            driver: $this->key(),
            metadata: $result->toArray(),
        );
    }

    private function normalizeContext(ExecutionContextData $context): ExecutionContextData
    {
        return new ExecutionContextData(
            contact: $context->contact,
            voucherCode: $context->voucherCode,
            meta: $this->redemptionMetadata($context),
            voucher: $context->voucher,
            instruction: $context->instruction,
            correlation: $context->correlation,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function redemptionMetadata(ExecutionContextData $context): array
    {
        $claim = (array) data_get($context->meta, 'claim', []);
        $metadata = [];

        if (($inputs = data_get($claim, 'inputs')) !== null) {
            $metadata['inputs'] = $inputs;
        }

        if (($mobile = data_get($claim, 'mobile')) !== null) {
            $metadata['mobile'] = $mobile;
        }

        if (($amount = data_get($claim, 'amount')) !== null) {
            $metadata['amount'] = $amount;
        }

        $bankCode = data_get($claim, 'bank_account.bank_code');
        $accountNumber = data_get($claim, 'bank_account.account_number');

        if (is_scalar($bankCode) && is_scalar($accountNumber) && $bankCode !== '' && $accountNumber !== '') {
            $metadata['bank_account'] = sprintf('%s:%s', (string) $bankCode, (string) $accountNumber);
        }

        foreach ((array) data_get($context->meta, 'redemption', []) as $key => $value) {
            if (is_string($key)) {
                $metadata[$key] = $value;
            }
        }

        return $metadata;
    }

    /**
     * @param  array<string, mixed>  $disbursement
     * @return array<int, array<string, mixed>>
     */
    private function providerReferences(array $disbursement): array
    {
        return array_values(array_filter([
            [
                'type' => 'provider_reference',
                'value' => $disbursement['provider_reference'] ?? null,
            ],
            [
                'type' => 'provider_transaction_id',
                'value' => $disbursement['provider_transaction_id'] ?? null,
            ],
            [
                'type' => 'reference_number',
                'value' => $disbursement['reference_number'] ?? null,
            ],
        ], static fn (array $reference): bool => is_string($reference['value'] ?? null) && $reference['value'] !== ''));
    }
}
