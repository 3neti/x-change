<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\Cash\Contracts\CashWithdrawalValidationContract;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Adapters\VoucherWithdrawableInstrumentAdapter;
use LBHurtado\XChange\Contracts\WithdrawalValidationContract;

/**
 * Withdrawal validation service (legacy wrapper).
 *
 * ⚠️ Refactor Status: Adapter Layer (Phase 1 – Cash Extraction)
 *
 * This class previously contained core business logic for:
 * - Withdrawal validation rules
 * - Open-slice constraints
 * - Amount validation
 *
 * That logic has been extracted to the `3neti/cash` package:
 * - LBHurtado\Cash\Contracts\CashWithdrawalValidationContract
 * - LBHurtado\Cash\Services\DefaultCashWithdrawalValidationService
 *
 * Current Responsibility:
 * - Accepts Voucher
 * - Adapts it to WithdrawableInstrumentContract
 * - Delegates validation to the cash package
 *
 * Important:
 * - Do NOT add new validation rules here
 * - Extend validation logic in the cash package instead
 *
 * Integration Notes:
 * - Retained for backward compatibility with existing x-change flows
 *
 * Future Plan:
 * - Will be deprecated once direct cash usage is adopted
 * - Scheduled for removal after migration completion
 *
 * @internal Adapter layer for cash extraction
 */
class DefaultWithdrawalValidationService implements WithdrawalValidationContract
{
    public function __construct(
        protected CashWithdrawalValidationContract $validator,
    ) {}

    public function validate(Voucher $voucher, array $payload): void
    {
        $instrument = new VoucherWithdrawableInstrumentAdapter($voucher);

        $this->validator->validate($instrument, $payload);
    }
}
