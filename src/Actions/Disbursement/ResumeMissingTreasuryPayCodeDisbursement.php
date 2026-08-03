<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Disbursement;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Services\Execution\TreasuryBackedPayCodeDisbursement;
use RuntimeException;

final readonly class ResumeMissingTreasuryPayCodeDisbursement
{
    public function __construct(
        private TreasuryBackedPayCodeDisbursement $disbursements,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(string $code, bool $confirmedNoProviderTransfer): array
    {
        if (! $confirmedNoProviderTransfer) {
            throw new RuntimeException(
                'Explicit confirmation that no provider transfer exists is required.',
            );
        }

        $voucher = Voucher::query()
            ->where('code', mb_strtoupper(trim($code)))
            ->firstOrFail();

        if (
            $voucher->redeemed_at === null
            || data_get($voucher->metadata, 'treasury.pay_code_reservation.status') !== 'reserved'
            || DisbursementReconciliation::query()->where('voucher_id', $voucher->getKey())->exists()
        ) {
            throw new RuntimeException(
                'The Pay Code is not eligible for missing Treasury disbursement recovery.',
            );
        }

        $voucher = $this->disbursements->handle($voucher);
        $reconciliation = DisbursementReconciliation::query()
            ->where('voucher_id', $voucher->getKey())
            ->latest('id')
            ->firstOrFail();

        return [
            'schema' => 'x-change.missing-treasury-disbursement-recovery.v1',
            'success' => $reconciliation->status !== 'failed',
            'pay_code' => $voucher->code,
            'provider' => $reconciliation->provider,
            'provider_reference' => $reconciliation->provider_reference,
            'provider_transaction_id' => $reconciliation->provider_transaction_id,
            'status' => $reconciliation->status,
            'reservation_status' => data_get(
                $voucher->refresh()->metadata,
                'treasury.pay_code_reservation.status',
            ),
        ];
    }
}
