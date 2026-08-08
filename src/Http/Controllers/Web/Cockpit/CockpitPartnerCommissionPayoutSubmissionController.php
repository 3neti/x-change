<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Actions\Commercial\SubmitPartnerCommissionPayoutBatch;
use LBHurtado\XChange\Http\Requests\Cockpit\SubmitPartnerCommissionPayoutBatchRequest;
use LBHurtado\XChange\Models\PartnerCommissionPayoutBatch;

final class CockpitPartnerCommissionPayoutSubmissionController extends Controller
{
    public function __construct(private readonly SubmitPartnerCommissionPayoutBatch $batches) {}

    public function store(
        SubmitPartnerCommissionPayoutBatchRequest $request,
        PartnerCommissionPayoutBatch $commissionPayoutBatch,
    ): RedirectResponse {
        abort_unless((bool) config('x-change.commercial.operations.live_provider_calls_enabled', false), 403);
        $operator = $request->user();
        abort_unless($operator instanceof Model, 403);
        $this->batches->execute(
            $operator,
            $commissionPayoutBatch,
            (string) $request->validated('idempotency_key'),
        );

        return back()->with('success', 'Partner commission payout submitted to the provider.');
    }
}
