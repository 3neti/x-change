<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use LBHurtado\XChange\Models\DisbursementReconciliation;

final class DisbursementRejected
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public DisbursementReconciliation $reconciliation,
    ) {}
}
