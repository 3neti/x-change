<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Claim;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use LBHurtado\FormFlowManager\Services\FormFlowService;
use LBHurtado\FormHandlerDocument\Contracts\DocumentAccess;
use LBHurtado\FormHandlerDocument\Data\DocumentUploadContext;
use LBHurtado\FormHandlerDocument\Models\DocumentSubmission;
use LBHurtado\Voucher\Models\Voucher;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ClaimDocumentAccess implements DocumentAccess
{
    public function __construct(private readonly FormFlowService $flows) {}

    public function grant(Request $request, Voucher $voucher, string $flowId): void
    {
        $state = $this->flows->getFlowState($flowId);
        abort_unless(is_array($state) && data_get($state, 'instructions.metadata.voucher_code') === (string) $voucher->code, 404);
        $request->session()->put($this->grantKey($flowId), [
            'flow_id' => $flowId,
            'voucher_code' => (string) $voucher->code,
            'nonce' => Str::random(64),
            'actor' => $request->user()?->getAuthIdentifier(),
        ]);
    }

    public function authorizeUpload(Request $request, string $flowId, string $documentKey): DocumentUploadContext
    {
        $state = $this->flows->getFlowState($flowId);
        $grant = $request->session()->get($this->grantKey($flowId));
        $code = data_get($state, 'instructions.metadata.voucher_code');
        abort_unless(is_array($state) && in_array($state['status'] ?? null, ['active', 'completed'], true)
            && is_string($code) && is_array($grant) && ($grant['flow_id'] ?? null) === $flowId
            && ($grant['voucher_code'] ?? null) === $code && is_string($grant['nonce'] ?? null)
            && ($grant['actor'] ?? null) === $request->user()?->getAuthIdentifier(), 404);

        $voucher = Voucher::query()->where('code', $code)->firstOrFail();
        $fields = (array) data_get($voucher->metadata, 'instructions.inputs.fields', []);
        $fields = array_map(static fn (mixed $field): mixed => $field instanceof \BackedEnum ? $field->value : $field, $fields);
        abort_unless(in_array('documents', $fields, true), 404);
        $definition = collect((array) data_get($voucher->metadata, 'instructions.inputs.documents', []))
            ->first(fn (mixed $item): bool => is_array($item) && ($item['key'] ?? null) === $documentKey);
        $accepted = is_array($definition) ? ($definition['accept'] ?? null) : null;
        abort_unless(is_array($accepted) && $accepted !== [], 404);

        $key = (string) config('app.key');
        abort_if($key === '', 404);

        return new DocumentUploadContext(
            hash_hmac('sha256', 'flow:'.$flowId, $key),
            hash_hmac('sha256', 'participant:'.$grant['nonce'], $key),
            $documentKey,
            $accepted,
        );
    }

    public function authorizeRead(Request $request, DocumentSubmission $submission): void
    {
        foreach ((array) $request->session()->get('x-change.claim-document', []) as $grant) {
            if (! is_array($grant) || ! is_string($grant['flow_id'] ?? null)) {
                continue;
            }
            try {
                $scope = $this->authorizeUpload($request, $grant['flow_id'], $submission->document_key);
            } catch (NotFoundHttpException) {
                continue;
            }
            if (hash_equals($scope->flowHash, $submission->flow_hash)
                && hash_equals($scope->participantHash, $submission->participant_hash)) {
                return;
            }
        }

        abort(404);
    }

    private function grantKey(string $flowId): string
    {
        return 'x-change.claim-document.'.hash('sha256', $flowId);
    }
}
