<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use LBHurtado\SettlementEnvelope\Services\EnvelopeService;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Http\Requests\ReviewEnvelopeDocumentRequest;

final class CockpitEnvelopeDocumentReviewController extends Controller
{
    public function __invoke(ReviewEnvelopeDocumentRequest $request, string $code, string $attachment, EnvelopeService $envelopes): Response
    {
        $voucher = Voucher::query()->where('code', strtoupper(trim($code)))->firstOrFail();
        $actor = $request->user();
        abort_unless($actor instanceof Model && $voucher->owner_type === $actor->getMorphClass()
            && (string) $voucher->owner_id === (string) $actor->getKey(), 404);
        $document = $voucher->envelope()->firstOrFail()->attachments()->findOrFail($attachment);
        $input = $request->validated();
        try {
            $envelopes->reviewAttachment($document, $input['decision'], $actor, $input['reason'] ?? null, requirePending: true);
        } catch (\LogicException) {
            abort(409, 'This document has already been reviewed.');
        }

        return response()->noContent()->header('Cache-Control', 'no-store, private');
    }
}
