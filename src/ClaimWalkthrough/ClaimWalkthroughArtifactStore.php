<?php

declare(strict_types=1);

namespace LBHurtado\XChange\ClaimWalkthrough;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

final class ClaimWalkthroughArtifactStore
{
    public function __construct(private readonly Filesystem $files) {}

    /**
     * @return array{run_id: string, root: string, screenshots: string, storyboard_frames: string}
     */
    public function prepare(string $scenario, ?string $runId = null): array
    {
        $resolvedRunId = $runId ?: Str::slug($scenario).'-'.now()->format('Ymd-His');
        $root = storage_path('app/x-change/walkthroughs/'.$resolvedRunId);

        return $this->prepareAt($resolvedRunId, $root);
    }

    /**
     * @return array{run_id: string, root: string, screenshots: string, storyboard_frames: string}
     */
    public function prepareAt(string $runId, string $root): array
    {
        $this->files->ensureDirectoryExists($root);
        $this->files->ensureDirectoryExists($root.'/screenshots');
        $this->files->ensureDirectoryExists($root.'/storyboard-frames');

        return [
            'run_id' => $runId,
            'root' => $root,
            'screenshots' => $root.'/screenshots',
            'storyboard_frames' => $root.'/storyboard-frames',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function writeJson(string $path, array $payload): void
    {
        $this->files->put(
            $path,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function readJson(string $path): array
    {
        $payload = json_decode($this->readText($path), true);

        return is_array($payload) ? $payload : [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $events
     */
    public function writeJsonLines(string $path, array $events): void
    {
        $lines = array_map(
            fn (array $event): string => json_encode($event, JSON_UNESCAPED_SLASHES),
            $events
        );

        $this->files->put($path, implode(PHP_EOL, $lines).PHP_EOL);
    }

    public function writeHtml(string $path, string $html): void
    {
        $this->files->put($path, $html);
    }

    public function writeText(string $path, string $text): void
    {
        $this->files->put($path, $text);
    }

    public function readText(string $path): string
    {
        return $this->files->get($path);
    }

    public function exists(string $path): bool
    {
        return $this->files->exists($path);
    }

    public function writePdf(string $path, string $pdf): void
    {
        $this->files->put($path, $pdf);
    }
}
