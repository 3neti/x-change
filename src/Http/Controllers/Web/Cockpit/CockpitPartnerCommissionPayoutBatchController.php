<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Actions\Commercial\RequestPartnerCommissionPayoutBatch;
use LBHurtado\XChange\Data\Commercial\PartnerCommissionPayoutBatchRequestData;
use LBHurtado\XChange\Http\Requests\Cockpit\StorePartnerCommissionPayoutBatchRequest;

final class CockpitPartnerCommissionPayoutBatchController extends Controller
{
    public function __construct(private readonly RequestPartnerCommissionPayoutBatch $batches) {}

    public function store(StorePartnerCommissionPayoutBatchRequest $request): RedirectResponse
    {
        $operator = $request->user();
        abort_unless($operator instanceof Model, 403);
        $validated = $request->validated();
        $this->batches->execute($operator, new PartnerCommissionPayoutBatchRequestData(
            reference: (string) $validated['reference'],
            partnerReference: (string) $validated['partner_reference'],
            provider: (string) $validated['provider'],
            connectionReference: (string) $validated['connection_reference'],
            currency: (string) $validated['currency'],
            periodStartedAt: (string) $validated['period_started_at'],
            periodEndedAt: (string) $validated['period_ended_at'],
            idempotencyKey: (string) $validated['idempotency_key'],
        ));

        return back()->with('success', 'Partner commission payout submitted for independent approval.');
    }
}
