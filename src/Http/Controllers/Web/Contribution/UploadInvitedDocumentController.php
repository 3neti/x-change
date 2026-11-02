<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Contribution;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use LBHurtado\SettlementEnvelope\Models\EnvelopeContributionToken;
use LBHurtado\SettlementEnvelope\Services\UploadInvitedContribution;
use LBHurtado\XChange\Http\Requests\UploadInvitedDocumentRequest;
use LBHurtado\XChange\Services\Settlement\ContributionPasswordSession;

final class UploadInvitedDocumentController extends Controller
{
    public function __invoke(UploadInvitedDocumentRequest $request, ContributionPasswordSession $passwords, UploadInvitedContribution $upload): JsonResponse
    {
        $input = $request->validated();
        $token = EnvelopeContributionToken::query()->where('token', $input['token'])->first();
        abort_unless($token !== null && $passwords->isVerified($token, $request->session()), 404);
        $envelope = $token->envelope()->firstOrFail();
        $document = $upload->handle($envelope, $token, $input['document_type'], $input['file'],
            fn (EnvelopeContributionToken $locked): bool => $passwords->isVerified($locked, $request->session()));

        return response()->json([
            'id' => $document->getKey(), 'document_type' => $document->doc_type, 'review_status' => $document->review_status,
        ], 201)->header('Cache-Control', 'no-store, private');
    }
}
