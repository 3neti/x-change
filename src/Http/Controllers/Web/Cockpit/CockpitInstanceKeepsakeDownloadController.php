<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use LBHurtado\XChange\Contracts\Keepsake\InstanceKeepsakeAccessContract;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class CockpitInstanceKeepsakeDownloadController extends Controller
{
    public function __construct(private readonly InstanceKeepsakeAccessContract $access) {}

    public function __invoke(Request $request, string $reference): StreamedResponse
    {
        $diskName = trim((string) config('x-change.instance_keepsake.disk', 'local'));
        $directory = trim((string) config('x-change.instance_keepsake.directory', 'x-change/instance-keepsakes'), '/');
        $metadataPath = $directory.'/'.$reference.'/grant.json';
        $disk = Storage::disk($diskName);

        abort_unless($disk->exists($metadataPath), 404);
        $grant = json_decode((string) $disk->get($metadataPath), true);
        $actor = $request->user();

        abort_unless(
            is_array($grant)
            && ($grant['published'] ?? false) === true
            && $actor !== null
            && $this->access->canDownload($actor, $grant),
            404,
        );
        abort_unless(blank($grant['consumed_at'] ?? null), 404);
        abort_unless(
            CarbonImmutable::parse((string) ($grant['expires_at'] ?? '1970-01-01'))->isFuture(),
            404,
        );

        return Cache::lock('x-change:instance-keepsake-download:'.hash('sha256', $reference), 30)
            ->block(3, function () use ($disk, $metadataPath): StreamedResponse {
                $fresh = json_decode((string) $disk->get($metadataPath), true);

                abort_unless(
                    is_array($fresh)
                    && ($fresh['published'] ?? false) === true
                    && blank($fresh['consumed_at'] ?? null),
                    404,
                );
                $archivePath = (string) $fresh['archive_path'];
                $integrityStream = $disk->readStream($archivePath);
                abort_unless(is_resource($integrityStream), 404);
                $hash = hash_init('sha256');
                hash_update_stream($hash, $integrityStream);
                fclose($integrityStream);
                abort_unless(
                    hash_equals((string) ($fresh['archive_sha256'] ?? ''), hash_final($hash)),
                    404,
                );
                $stream = $disk->readStream($archivePath);
                abort_unless(is_resource($stream), 404);
                $fresh['consumed_at'] = CarbonImmutable::now('UTC')->toIso8601String();
                abort_unless($disk->put($metadataPath, json_encode($fresh, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)), 404);

                return response()->streamDownload(
                    static function () use ($stream): void {
                        try {
                            fpassthru($stream);
                        } finally {
                            fclose($stream);
                        }
                    },
                    (string) $fresh['archive_filename'],
                    [
                        'Content-Type' => 'application/octet-stream',
                        'Cache-Control' => 'no-store, private, max-age=0',
                        'Pragma' => 'no-cache',
                        'X-Content-Type-Options' => 'nosniff',
                        'X-Robots-Tag' => 'noindex, nofollow, noarchive',
                    ],
                );
            });
    }
}
