<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Actions\Commercial\PreparePartnerCommissionPayoutRetry;
use LBHurtado\XChange\Http\Requests\Cockpit\RetryPartnerCommissionPayoutBatchRequest;
use LBHurtado\XChange\Models\CommercialPartnerDestinationRevision;
use LBHurtado\XChange\Models\PartnerCommissionPayoutBatch;

final class CockpitPartnerCommissionPayoutRetryController extends Controller
{
    public function __construct(private readonly PreparePartnerCommissionPayoutRetry $batches) {}

    public function store(
        RetryPartnerCommissionPayoutBatchRequest $request,
        PartnerCommissionPayoutBatch $commissionPayoutBatch,
    ): RedirectResponse {
        $operator = $request->user();
        abort_unless($operator instanceof Model, 403);
        $destination = CommercialPartnerDestinationRevision::query()->findOrFail(
            (int) $request->validated('destination_revision_id'),
        );
        $this->batches->execute($operator, $commissionPayoutBatch, $destination);

        return back()->with('success', 'A new payout attempt is ready against the approved destination.');
    }
}
