<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use LBHurtado\SettlementEnvelope\Services\EnvelopeService;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Http\Requests\ReviewEnvelopeDocumentRequest;
use LBHurtado\XChange\Services\Settlement\V2EvidenceEventJournal;

final class CockpitEnvelopeDocumentReviewController extends Controller
{
    public function __invoke(ReviewEnvelopeDocumentRequest $request, string $code, string $attachment, EnvelopeService $envelopes, V2EvidenceEventJournal $events): Response
    {
        $voucher = Voucher::query()->where('code', strtoupper(trim($code)))->firstOrFail();
        $actor = $request->user();
        abort_unless($actor instanceof Model && $voucher->owner_type === $actor->getMorphClass()
            && (string) $voucher->owner_id === (string) $actor->getKey(), 404);
        $input = $request->validated();
        try {
            DB::transaction(function () use ($voucher, $actor, $attachment, $input, $envelopes, $events): void {
                $locked = Voucher::query()->lockForUpdate()->findOrFail($voucher->getKey());
                abort_unless($locked->owner_type === $actor->getMorphClass() && (string) $locked->owner_id === (string) $actor->getKey(), 404);
                $document = $locked->envelope()->lockForUpdate()->firstOrFail()->attachments()->findOrFail($attachment);
                $envelopes->reviewAttachment($document, $input['decision'], $actor, $input['reason'] ?? null, requirePending: true);
                $events->settlementReadyAfterReview($locked->fresh());
            });
        } catch (\LogicException) {
            abort(409, 'This document has already been reviewed.');
        }

        return response()->noContent()->header('Cache-Control', 'no-store, private');
    }
}
