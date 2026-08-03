<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use LBHurtado\SettlementEnvelope\Models\EnvelopeAttachment;
use LBHurtado\XChange\Contracts\VoucherAccessContract;
use LBHurtado\XChange\Services\Cockpit\CockpitPayCodeDetailAccess;
use LBHurtado\XChange\Services\Cockpit\CockpitPayCodeEvidenceAccessJournal;
use Symfony\Component\HttpFoundation\HeaderUtils;

final class CockpitPayCodeEvidenceController extends Controller
{
    public function __construct(
        private readonly VoucherAccessContract $vouchers,
        private readonly CockpitPayCodeDetailAccess $access,
        private readonly CockpitPayCodeEvidenceAccessJournal $journal,
    ) {}

    public function __invoke(
        Request $request,
        string $code,
        string $source,
        int $evidence,
    ): Response {
        $voucher = $this->vouchers->findByCodeOrFail($code);
        $actor = $request->user();

        abort_unless(
            $actor !== null && $this->access->canView($actor, $voucher),
            404,
        );

        [$contents, $mimeType, $filename, $evidenceType] = match ($source) {
            'input' => $this->inputEvidence($voucher, $evidence),
            'envelope' => $this->envelopeEvidence($voucher, $evidence),
            default => abort(404),
        };

        $this->journal->record(
            voucher: $voucher,
            actor: $actor,
            source: $source,
            evidenceId: $evidence,
            evidenceType: $evidenceType,
        );

        return response($contents, 200, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_INLINE,
                $filename,
            ),
            'Cache-Control' => 'no-store, private, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ]);
    }

    /** @return array{string, string, string, string} */
    private function inputEvidence(mixed $voucher, int $evidence): array
    {
        $input = $voucher->inputs()->findOrFail($evidence);
        $name = (string) $input->getAttribute('name');

        abort_unless(in_array($name, [
            'selfie',
            'signature',
            'kyc_id_front',
            'kyc_id_back',
        ], true), 404);

        [$contents, $declaredMime] = $this->decodeImage(
            (string) $input->getAttribute('value'),
        );
        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents) ?: '';

        abort_unless(in_array($mimeType, [
            'image/jpeg',
            'image/png',
            'image/webp',
        ], true), 404);
        abort_unless($declaredMime === null || $declaredMime === $mimeType, 404);

        $extension = match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        };

        return [$contents, $mimeType, $name.'.'.$extension, $name];
    }

    /** @return array{string, string, string, string} */
    private function envelopeEvidence(mixed $voucher, int $evidence): array
    {
        $envelope = $voucher->envelope()->firstOrFail();
        $attachment = EnvelopeAttachment::query()
            ->whereBelongsTo($envelope, 'envelope')
            ->findOrFail($evidence);
        $mimeType = (string) $attachment->mime_type;

        abort_unless(in_array($mimeType, [
            'image/jpeg',
            'image/png',
            'image/webp',
            'application/pdf',
        ], true), 404);

        try {
            $contents = Storage::disk($attachment->disk)->get($attachment->file_path);
        } catch (FileNotFoundException) {
            abort(404);
        }

        abort_unless(hash_equals((string) $attachment->hash, hash('sha256', $contents)), 404);

        return [
            $contents,
            $mimeType,
            basename((string) $attachment->original_filename),
            (string) $attachment->doc_type,
        ];
    }

    /** @return array{string, string|null} */
    private function decodeImage(string $value): array
    {
        $declaredMime = null;

        if (preg_match('/^data:([^;]+);base64,(.+)$/s', $value, $matches) === 1) {
            $declaredMime = $matches[1];
            $value = $matches[2];
        }

        $contents = base64_decode(preg_replace('/\s+/', '', $value) ?? '', true);

        abort_unless(is_string($contents) && $contents !== '', 404);

        return [$contents, $declaredMime];
    }
}
