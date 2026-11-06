<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Contribution;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use LBHurtado\SettlementEnvelope\Models\EnvelopeContributionToken;
use LBHurtado\XChange\Services\Settlement\ContributionPasswordSession;

final class ContributionContextController extends Controller
{
    public function __invoke(Request $request, ContributionPasswordSession $passwords): JsonResponse
    {
        $input = $request->validate(['token' => ['required', 'uuid']]);
        $token = EnvelopeContributionToken::query()->where('token', $input['token'])->first();
        abort_unless($token !== null && $token->isValid(), 404);
        $verified = $passwords->isVerified($token, $request->session());
        $documents = $verified ? $token->envelope->attachments()->get()->filter(
            fn ($attachment): bool => (string) data_get($attachment->metadata, 'contribution_token_id') === (string) $token->getKey()
                && data_get($attachment->metadata, 'contributor_role') === 'invited',
        )->map(fn ($attachment): array => [
            'id' => $attachment->getKey(),
            'name' => $attachment->original_filename,
            'type' => $attachment->doc_type,
            'status' => $attachment->review_status,
            'rejection_reason' => $attachment->rejection_reason,
            'view_url' => route('x-change.contributions.documents.show', ['attachment' => $attachment->getKey()]),
        ])->values()->all() : [];

        return response()->json([
            'password_required' => $token->requiresPassword(),
            'verified' => $verified,
            'document_types' => $verified ? data_get($token->metadata, 'scope.document_types', []) : [],
            'documents' => $documents,
            'expires_at' => $token->expires_at->toIso8601String(),
        ])->header('Cache-Control', 'no-store, private');
    }
}
