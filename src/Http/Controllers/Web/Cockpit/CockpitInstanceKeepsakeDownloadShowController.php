<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use LBHurtado\XChange\Contracts\Keepsake\InstanceKeepsakeAccessContract;

final class CockpitInstanceKeepsakeDownloadShowController extends Controller
{
    public function __construct(private readonly InstanceKeepsakeAccessContract $access) {}

    public function __invoke(Request $request, string $reference): View
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
            && blank($grant['consumed_at'] ?? null)
            && $actor !== null
            && $this->access->canDownload($actor, $grant)
            && CarbonImmutable::parse((string) ($grant['expires_at'] ?? '1970-01-01'))->isFuture(),
            404,
        );

        return view('x-change::cockpit.instance-keepsakes.download', [
            'reference' => $reference,
            'expiresAt' => CarbonImmutable::parse((string) $grant['expires_at']),
        ]);
    }
}
