<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Http\Requests\StageIssuerInvoiceRequest;
use LBHurtado\XChange\Services\Settlement\IssuerInvoiceDrafts;

final class CockpitIssuerInvoiceDraftController extends Controller
{
    public function __invoke(StageIssuerInvoiceRequest $request, IssuerInvoiceDrafts $drafts): JsonResponse
    {
        $owner = $request->user();
        abort_unless($owner instanceof Model, 403);

        $reference = $drafts->create($owner, $request->file('invoice'));

        return response()->json(['reference' => $reference, 'document_id' => $drafts->documentId($owner, $reference)], 201)
            ->header('Cache-Control', 'no-store, private');
    }
}
