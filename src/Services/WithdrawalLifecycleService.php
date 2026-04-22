<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\XChange\Contracts\DisbursementReconciliationStoreContract;
use LBHurtado\XChange\Contracts\VoucherAccessContract;
use LBHurtado\XChange\Contracts\WithdrawalExecutionContract;
use LBHurtado\XChange\Contracts\WithdrawalLifecycleServiceContract;
use LBHurtado\XChange\Data\Reconciliation\DisbursementReconciliationData;
use LBHurtado\XChange\Data\Redemption\WithdrawPayCodeResultData;

class WithdrawalLifecycleService implements WithdrawalLifecycleServiceContract
{
    public function __construct(
        protected VoucherAccessContract $vouchers,
        protected WithdrawalExecutionContract $withdrawals,
        protected DisbursementReconciliationStoreContract $reconciliations,
    ) {}

    public function create(array $payload): mixed
    {
        $voucherCode = (string) ($payload['voucher_code'] ?? '');
        $voucher = $this->vouchers->findByCodeOrFail($voucherCode);

        $result = $this->withdrawals->withdraw($voucher, $payload);

        return $this->mapExecutionResult($result);
    }

    public function list(array $filters = []): array
    {
        $limit = isset($filters['limit']) && is_numeric($filters['limit'])
            ? (int) $filters['limit']
            : 50;

        $items = $this->reconciliations->getPending($limit);

        return collect($items)
            ->filter(fn (DisbursementReconciliationData $item) => $this->isWithdrawalRecord($item, $filters))
            ->map(fn (DisbursementReconciliationData $item) => $this->mapReconciliationSummary($item))
            ->values()
            ->all();
    }

    public function show(string $withdrawal): mixed
    {
        $record = $this->reconciliations->findById((int) $withdrawal);

        if (! $record instanceof DisbursementReconciliationData) {
            return [
                'id' => $withdrawal,
                'voucher_code' => null,
                'status' => 'unknown',
                'amount' => null,
                'currency' => null,
                'bank_code' => null,
                'account_number' => null,
                'messages' => [],
            ];
        }

        return $this->mapReconciliationDetail($record);
    }

    protected function mapExecutionResult(WithdrawPayCodeResultData $result): array
    {
        return [
            'id' => (string) data_get($result, 'disbursement.transaction_id', data_get($result, 'voucher_code')),
            'voucher_code' => (string) $result->voucher_code,
            'status' => (string) $result->status,
            'amount' => $result->disbursed_amount !== null
                ? (float) $result->disbursed_amount
                : (float) $result->requested_amount,
            'currency' => $result->currency !== null
                ? (string) $result->currency
                : 'PHP',
            'bank_code' => data_get($result, 'bank_account.bank_code') !== null
                ? (string) data_get($result, 'bank_account.bank_code')
                : null,
            'account_number' => data_get($result, 'bank_account.account_number') !== null
                ? (string) data_get($result, 'bank_account.account_number')
                : null,
            'messages' => collect($result->messages ?? [])
                ->map(fn ($value) => (string) $value)
                ->values()
                ->all(),
        ];
    }

    protected function mapReconciliationSummary(DisbursementReconciliationData $item): array
    {
        return [
            'id' => (string) $item->id,
            'voucher_code' => (string) $item->voucher_code,
            'status' => (string) $item->status,
            'amount' => (float) ($item->amount ?? 0.0),
            'currency' => (string) ($item->currency ?? 'PHP'),
        ];
    }

    protected function mapReconciliationDetail(DisbursementReconciliationData $item): array
    {
        return [
            'id' => (string) $item->id,
            'voucher_code' => (string) $item->voucher_code,
            'status' => (string) $item->status,
            'amount' => (float) ($item->amount ?? 0.0),
            'currency' => (string) ($item->currency ?? 'PHP'),
            'bank_code' => $item->bank_code !== null ? (string) $item->bank_code : null,
            'account_number' => $item->account_number_masked !== null ? (string) $item->account_number_masked : null,
            'messages' => array_values(array_filter([
                $item->review_reason,
                $item->error_message,
            ], fn ($value) => is_string($value) && $value !== '')),
        ];
    }

    /**
     * @param array<string,mixed> $filters
     */
    protected function isWithdrawalRecord(DisbursementReconciliationData $item, array $filters): bool
    {
        if ($item->claim_type !== 'withdraw') {
            return false;
        }

        if (isset($filters['voucher_code']) && is_string($filters['voucher_code']) && $filters['voucher_code'] !== '') {
            if ((string) $item->voucher_code !== $filters['voucher_code']) {
                return false;
            }
        }

        if (isset($filters['status']) && is_string($filters['status']) && $filters['status'] !== '') {
            if ((string) $item->status !== $filters['status']) {
                return false;
            }
        }

        return true;
    }
}
