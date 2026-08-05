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
use LBHurtado\XChange\Models\VoucherClaimEvidence;
use LBHurtado\XChange\Services\Cockpit\CockpitPayCodeDetailAccess;
use LBHurtado\XChange\Services\Cockpit\CockpitPayCodeEvidenceAccessJournal;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Throwable;

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
            'claim' => $this->claimEvidence($voucher, $evidence),
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
    private function claimEvidence(mixed $voucher, int $evidence): array
    {
        $item = VoucherClaimEvidence::query()
            ->where('voucher_id', $voucher->getKey())
            ->findOrFail($evidence);

        abort_unless(
            filled($item->artifact_disk)
            && filled($item->artifact_path)
            && filled($item->mime_type)
            && filled($item->sha256),
            404,
        );

        try {
            $disk = Storage::disk((string) $item->artifact_disk);

            abort_unless($disk->exists((string) $item->artifact_path), 404);
            $contents = $disk->get((string) $item->artifact_path);
        } catch (Throwable) {
            abort(404);
        }

        abort_unless(is_string($contents), 404);
        abort_unless(hash_equals((string) $item->sha256, hash('sha256', $contents)), 404);

        return [
            $contents,
            (string) $item->mime_type,
            $item->requirement_key.'.'.$this->extension((string) $item->mime_type),
            $item->requirement_key,
        ];
    }

    private function extension(string $mimeType): string
    {
        return match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => abort(404),
        };
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
            'location',
        ], true), 404);

        $value = (string) $input->getAttribute('value');

        if ($name === 'location') {
            $location = json_decode($value, true);

            abort_unless(is_array($location) && is_string($location['map'] ?? null), 404);
            $value = $location['map'];
        }

        [$contents, $declaredMime] = $this->decodeImage(
            $value,
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
