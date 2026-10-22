<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use LBHurtado\XChange\Contracts\RiderStampArtifactStoreContract;
use LBHurtado\XChange\Contracts\VoucherAccessContract;
use LBHurtado\XChange\Services\Cockpit\CockpitPayCodeDetailAccess;
use Throwable;

final class CockpitPayCodeStampController extends Controller
{
    public function __construct(
        private readonly VoucherAccessContract $vouchers,
        private readonly CockpitPayCodeDetailAccess $access,
        private readonly RiderStampArtifactStoreContract $artifacts,
    ) {}

    public function __invoke(Request $request, string $code, string $size, string $sha256): Response
    {
        $voucher = $this->vouchers->findByCode($code);

        abort_unless(
            $voucher !== null
                && $request->user() !== null
                && $this->access->canView($request->user(), $voucher),
            404,
        );

        $artifact = $this->artifacts->descriptor($voucher);
        abort_unless($artifact !== null && hash_equals($artifact->sha256, $sha256), 404);

        $headers = [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, max-age=3600',
            'ETag' => '"'.$sha256.'-'.$size.'"',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ];

        if ($size === 'full' && $request->query('download') === '1') {
            $safeCode = preg_replace('/[^A-Za-z0-9-]/', '', (string) $voucher->code);
            $headers['Content-Disposition'] = 'attachment; filename="Pay-Code-'.$safeCode.'.png"';
        }

        $card = $this->artifacts->read($voucher);
        abort_unless($card !== null, 404);

        if ($request->header('If-None-Match') === $headers['ETag']) {
            return response('', 304, $headers);
        }

        if ($size === 'full') {
            return response($card->contents, 200, $headers);
        }

        $disk = Storage::disk((string) config('x-change.claim.share.artifact.disk', 'local'));
        $path = 'x-change/claim/stamp-thumbnails/'.$sha256.'.png';

        try {
            $thumbnail = $disk->exists($path) ? $disk->get($path) : null;

            if (! is_string($thumbnail) || $thumbnail === '') {
                $thumbnail = $this->thumbnail($card->contents);
                $disk->put($path, $thumbnail);
            }
        } catch (Throwable) {
            abort(404);
        }

        return response($thumbnail, 200, $headers);
    }

    private function thumbnail(string $contents): string
    {
        $source = @imagecreatefromstring($contents);
        abort_unless($source !== false, 404);

        try {
            $target = imagecreatetruecolor(320, 168);
            abort_unless($target !== false, 404);

            try {
                imagealphablending($target, false);
                imagesavealpha($target, true);
                imagecopyresampled($target, $source, 0, 0, 0, 0, 320, 168, imagesx($source), imagesy($source));
                ob_start();
                imagepng($target);
                $thumbnail = ob_get_clean();
            } finally {
                imagedestroy($target);
            }
        } finally {
            imagedestroy($source);
        }

        abort_unless(is_string($thumbnail) && $thumbnail !== '', 404);

        return $thumbnail;
    }
}
