<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Actions\Commercial\RecordProviderCostBatch;
use LBHurtado\XChange\Data\Commercial\ProviderCostBatchEvidenceData;
use LBHurtado\XChange\Http\Requests\Cockpit\StoreCommercialProviderCostBatchRequest;

final class CockpitCommercialProviderCostBatchController extends Controller
{
    public function __construct(private readonly RecordProviderCostBatch $batches) {}

    public function store(StoreCommercialProviderCostBatchRequest $request): RedirectResponse
    {
        $operator = $request->user();
        abort_unless($operator instanceof Model, 403);
        $validated = $request->validated();
        $batch = $this->batches->execute($operator, new ProviderCostBatchEvidenceData(
            reference: (string) $validated['reference'],
            provider: (string) $validated['provider'],
            connectionReference: (string) $validated['connection_reference'],
            currency: (string) $validated['currency'],
            evidenceType: (string) $validated['evidence_type'],
            evidenceReference: (string) $validated['evidence_reference'],
            observedAmountMinor: (int) $validated['observed_amount_minor'],
            periodStartedAt: (string) $validated['period_started_at'],
            periodEndedAt: (string) $validated['period_ended_at'],
            observedAt: (string) $validated['observed_at'],
            idempotencyKey: (string) $validated['idempotency_key'],
        ));

        return back()->with(
            $batch->status->value === 'settled' ? 'success' : 'warning',
            $batch->status->value === 'settled'
                ? 'Provider cost batch settled against exact authoritative evidence.'
                : 'Provider cost evidence recorded for review; no accounting mutation occurred.',
        );
    }
}
