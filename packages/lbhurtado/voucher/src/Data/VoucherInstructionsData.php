<?php

namespace LBHurtado\Voucher\Data;

use Spatie\LaravelData\Data;
class VoucherInstructionsData extends Data
{
    public function __construct(
        public CashInstructionData $cash,
        public InputFieldsData $inputs,
        public FeedbackInstructionData $feedback,
        public RiderInstructionData $rider,
    ) {}
}
