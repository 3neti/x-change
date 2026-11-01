<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Contribution;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use LBHurtado\SettlementEnvelope\Models\EnvelopeAttachment;
use LBHurtado\SettlementEnvelope\Models\EnvelopeContributionToken;
use LBHurtado\XChange\Http\Requests\ViewContributionDocumentRequest;
use LBHurtado\XChange\Services\Settlement\ContributionPasswordSession;
use Throwable;

final class ViewContributionDocumentController extends Controller
{
    public function __invoke(ViewContributionDocumentRequest $request, string $attachment, ContributionPasswordSession $passwords): Response
    {
        $token = EnvelopeContributionToken::query()->where('token', $request->validated('token'))->first();
        abort_unless($token !== null && $passwords->isVerified($token, $request->session()), 404);

        $document = EnvelopeAttachment::query()->where('envelope_id', $token->envelope_id)->findOrFail($attachment);
        $actions = data_get($token->metadata, 'scope.actions');
        $types = data_get($token->metadata, 'scope.document_types');
        abort_unless(is_array($actions) && array_is_list($actions) && in_array('read_own', $actions, true)
            && is_array($types) && array_is_list($types) && in_array($document->doc_type, $types, true)
            && data_get($document->metadata, 'contributor_role') === 'invited'
            && (string) data_get($document->metadata, 'contribution_token_id') === (string) $token->getKey(), 404);
        $extension = match ($document->mime_type) {
            'application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', default => null,
        };
        abort_unless($extension !== null && config("filesystems.disks.{$document->disk}.visibility") === 'private', 404);
        try {
            $contents = Storage::disk($document->disk)->get($document->file_path);
        } catch (Throwable) {
            abort(404);
        }
        abort_unless(is_string($contents) && hash_equals((string) $document->hash, hash('sha256', $contents)), 404);

        return response($contents, 200, [
            'Content-Type' => $document->mime_type,
            'Content-Disposition' => 'inline; filename="document.'.$extension.'"',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ]);
    }
}
