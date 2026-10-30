<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Claim;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Settlement\VerifyPhilhealthSettlementAmount;
use LBHurtado\XChange\Services\VoucherCollectionProgressService;

final class ClaimSettlementReviewController extends Controller
{
    public function show(Request $request, string $code, VoucherCollectionProgressService $progress): Response
    {
        $voucher = $this->voucher($code);
        Gate::forUser($request->user())->authorize('x-change.settlement.verify-amount', $voucher);
        $claim = $voucher->claims()->whereKey(data_get($voucher->metadata, 'settlement_envelope.attestation.claim_id'))->firstOrFail();

        return Inertia::render('x-change/claim/SettlementReview', [
            'code' => $voucher->code,
            'amountMinor' => (int) round((float) $voucher->target_amount * 100),
            'reviewed' => is_array(data_get($voucher->metadata, 'settlement_envelope.amount_review')),
            'paid' => $progress->compute($voucher)->is_fully_collected,
            'evidenceManifest' => data_get($voucher->metadata, 'settlement_envelope.attestation.evidence_manifest_sha256'),
            'evidence' => $claim->evidence()->get()->map(fn ($item): array => [
                'requirement' => $item->requirement_key,
                'summary' => $item->summary,
                'status' => $item->status->value,
            ])->all(),
        ]);
    }

    public function store(Request $request, string $code, VerifyPhilhealthSettlementAmount $review): RedirectResponse
    {
        $voucher = $this->voucher($code);
        Gate::forUser($request->user())->authorize('x-change.settlement.verify-amount', $voucher);
        $validated = $request->validate([
            'amount_minor' => ['required', 'integer', 'min:1'],
            'evidence_manifest' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
        ]);
        $claimId = data_get($voucher->metadata, 'settlement_envelope.attestation.claim_id');
        abort_unless($claimId, 404);
        try {
            $review->handle($voucher, $request->user(), (int) $validated['amount_minor'], 'claim-review:'.$claimId, $validated['evidence_manifest']);
        } catch (\LogicException $exception) {
            throw ValidationException::withMessages(['evidence_manifest' => $exception->getMessage()]);
        }

        return redirect()->route('x-change.claim.settlement-review.show', ['code' => $voucher->code]);
    }

    private function voucher(string $code): Voucher
    {
        $voucher = Voucher::query()->where('code', strtoupper(trim($code)))->firstOrFail();
        abort_unless(data_get($voucher->metadata, 'instructions.execution.schema') === 'voucher.execution.v2'
            && data_get($voucher->metadata, 'instructions.execution.settlement.driver') === 'philhealth-bst'
            && is_array(data_get($voucher->metadata, 'instructions.execution.events.review')), 404);

        return $voucher;
    }
}
