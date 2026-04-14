<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Disbursement;

use Illuminate\Console\Command;
use LBHurtado\XChange\Console\Concerns\InteractsWithJsonOutput;
use LBHurtado\XChange\Contracts\DisbursementReconciliationContract;
use LBHurtado\XChange\Contracts\VoucherAccessContract;
use LBHurtado\XChange\Data\Reconciliation\DisbursementReconciliationData;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Services\DefaultDisbursementStatusFetcherService;
use LBHurtado\XChange\Services\DefaultDisbursementStatusResolverService;

class CheckDisbursementStatusCommand extends Command
{
    use InteractsWithJsonOutput;

    protected $signature = 'xchange:disbursement:check
    {code : Voucher or pay code}
    {--sync : Persist fetched provider status to reconciliation}
    {--json : Output JSON}
    {--pretty : Pretty-print JSON output}';

    protected $description = 'Check the latest disbursement status for a specific voucher.';

    public function handle(
        VoucherAccessContract $vouchers,
        DefaultDisbursementStatusFetcherService $fetcher,
        DefaultDisbursementStatusResolverService $resolver,
        DisbursementReconciliationContract $reconciler,
    ): int {
        $voucher = $vouchers->findByCodeOrFail((string) $this->argument('code'));

        $reconciliation = DisbursementReconciliation::query()
            ->where('voucher_code', $voucher->code)
            ->latest('id')
            ->first();

        if (! $reconciliation) {
            $payload = [
                'voucher_code' => $voucher->code,
                'found' => false,
                'message' => 'No disbursement reconciliation record found.',
            ];

            $this->renderPayload($payload, 'No disbursement record found.');

            return self::FAILURE;
        }

        if ($this->option('sync')) {
            $sync = $reconciler->reconcile($reconciliation->fresh());
            $reconciliation->refresh();

            $metadata = is_array($sync['raw'] ?? null) ? $sync['raw'] : [];
            $fetchedStatus = $sync['fetched_status'] ?? null;
            $resolvedStatus = $sync['resolved_status'] ?? null;
        } else {
            $fetched = $fetcher->fetch(
                DisbursementReconciliationData::from($reconciliation->toArray())
            );

            $metadata = $this->extractMetadata($fetched);
            $fetchedStatus = $fetched['status'] ?? null;
            $resolvedStatus = $resolver->resolveFromFetchedStatus($fetchedStatus, $metadata);
        }

        $payload = [
            'voucher_code' => $voucher->code,
            'found' => true,
            'reconciliation_id' => $reconciliation->id,
            'provider' => $reconciliation->provider,
            'provider_reference' => $reconciliation->provider_reference,
            'provider_transaction_id' => $reconciliation->provider_transaction_id,
            'current_status' => $reconciliation->status,
            'fetched_status' => $fetchedStatus,
            'resolved_status' => $resolvedStatus,
            'internal_status' => $reconciliation->internal_status,
            'needs_review' => (bool) $reconciliation->needs_review,
            'error_message' => $reconciliation->error_message,
            'reference_number' => data_get($metadata, 'reference_number'),
            'operation_id' => data_get($metadata, 'operation_id'),
            'provider_updated_at' => data_get($metadata, 'updated'),
            'provider_created_at' => data_get($metadata, 'date'),
            'settlement_rail' => data_get($metadata, 'settlement_rail'),
            'destination_account' => [
                'bank_code' => data_get($metadata, 'destination_account.bank_code'),
                'account_number_masked' => $this->maskAccountNumber(
                    data_get($metadata, 'destination_account.account_number')
                ),
            ],
            'amount' => [
                'currency' => data_get($metadata, 'amount.cur'),
                'value' => data_get($metadata, 'amount.num'),
            ],
            'status_details' => $this->normalizeStatusDetails(
                data_get($metadata, 'status_details', [])
            ),
            'raw' => $metadata,
        ];

        $this->renderPayload($payload, 'Disbursement status checked successfully.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $fetched
     * @return array<string, mixed>
     */
    protected function extractMetadata(array $fetched): array
    {
        $metadata = $fetched['raw'] ?? $fetched['metadata'] ?? null;

        return is_array($metadata) ? $metadata : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeStatusDetails(mixed $details): array
    {
        if (! is_array($details)) {
            return [];
        }

        return array_values(array_map(
            static function ($item): array {
                if (! is_array($item)) {
                    return [];
                }

                return [
                    'status' => $item['status'] ?? null,
                    'updated' => $item['updated'] ?? null,
                ];
            },
            $details
        ));
    }

    protected function maskAccountNumber(?string $accountNumber): ?string
    {
        if ($accountNumber === null || $accountNumber === '') {
            return null;
        }

        $length = strlen($accountNumber);

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', $length - 4).substr($accountNumber, -4);
    }
}
