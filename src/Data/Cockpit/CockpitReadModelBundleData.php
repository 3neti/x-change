<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Cockpit;

use Spatie\LaravelData\Data;

class CockpitReadModelBundleData extends Data
{
    public function __construct(
        public readonly ?string $code,
        public readonly CockpitVoucherReadModelData $voucher,
        public readonly CockpitExecutionReadModelData $execution,
        public readonly CockpitJournalReadModelData $journal,
        public readonly CockpitActionReadModelData $actions,
        public readonly CockpitFeedbackReadModelData $feedback,
    ) {}
}
