<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Claim;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use LBHurtado\FormHandlerDocument\Models\DocumentSubmission;
use LBHurtado\Voucher\Models\Voucher;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ClaimDocumentEvidence
{
    private ?Request $request = null;

    private ?string $flowId = null;

    private ?string $voucherCode = null;

    public function establish(Request $request, Voucher $voucher, string $flowId): void
    {
        $this->request = $request;
        $this->flowId = $flowId;
        $this->voucherCode = (string) $voucher->code;
    }

    public function isComplete(Voucher $voucher, mixed $value): bool
    {
        $definitions = data_get($voucher->metadata, 'instructions.inputs.documents', []);
        if (! is_array($definitions) || $definitions === [] || ! is_array($value) || ! array_is_list($value)) {
            return false;
        }
        $provided = [];
        foreach ($value as $item) {
            if (! is_array($item) || ! is_string($item['key'] ?? null) || ! is_string($item['reference'] ?? null)
                || isset($provided[$item['key']])) {
                return false;
            }
            $provided[$item['key']] = $item['reference'];
        }
        foreach ($definitions as $definition) {
            $key = is_array($definition) ? ($definition['key'] ?? null) : null;
            if (! is_string($key)) {
                return false;
            }
            $reference = $provided[$key] ?? null;
            if ($reference === null) {
                if (($definition['required'] ?? null) !== false) {
                    return false;
                }

                continue;
            }
            if ($this->request === null || $this->flowId === null || $this->voucherCode !== (string) $voucher->code) {
                return false;
            }
            try {
                $scope = app(ClaimDocumentAccess::class)->authorizeUpload($this->request, $this->flowId, $key);
            } catch (NotFoundHttpException) {
                return false;
            }
            $submission = DocumentSubmission::query()->where('reference', $reference)
                ->where('flow_hash', $scope->flowHash)->where('participant_hash', $scope->participantHash)
                ->where('document_key', $key)->where('status', 'linked')->first();
            $media = $submission?->getFirstMedia('documents');
            if ($submission === null || $media === null || ! in_array($submission->mime_type, $scope->acceptedMimeTypes, true)
                || ! $voucher->envelope()->whereHas('attachments', fn ($query) => $query->where('document_id', $reference))->exists()
                || ! Storage::disk($media->disk)->exists($media->getPathRelativeToRoot())) {
                return false;
            }
            $stream = Storage::disk($media->disk)->readStream($media->getPathRelativeToRoot());
            if (! is_resource($stream)) {
                return false;
            }
            $hash = hash_init('sha256');
            try {
                hash_update_stream($hash, $stream);
            } finally {
                fclose($stream);
            }
            if (! hash_equals($submission->sha256, hash_final($hash))) {
                return false;
            }
            unset($provided[$key]);
        }

        return $provided === [];
    }
}
