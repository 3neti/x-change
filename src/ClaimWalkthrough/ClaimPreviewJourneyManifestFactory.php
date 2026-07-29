<?php

declare(strict_types=1);

namespace LBHurtado\XChange\ClaimWalkthrough;

use Illuminate\Filesystem\Filesystem;

final class ClaimPreviewJourneyManifestFactory
{
    public function __construct(
        private readonly Filesystem $files,
    ) {}

    /**
     * @param  array<string, mixed>  $report
     * @param  array<string, mixed>  $scenario
     * @return array<string, mixed>
     */
    public function fromReport(array $report, array $scenario): array
    {
        $canonical = $this->canonicalSteps($scenario);
        $storyboard = $this->storyboard($report);
        $captured = collect(data_get($storyboard, 'checkpoints', []))
            ->filter(fn (mixed $checkpoint): bool => is_array($checkpoint))
            ->mapWithKeys(function (array $checkpoint): array {
                $key = $this->canonicalKey((string) ($checkpoint['key'] ?? ''));

                return $key === '' ? [] : [$key => $checkpoint];
            });
        $root = $this->artifactRoot($report);

        $steps = collect($canonical)
            ->map(function (array $step, int $index) use ($captured, $root): array {
                $checkpoint = $captured->get($step['key']);

                return $this->step(
                    step: $step,
                    sequence: $index + 1,
                    checkpoint: is_array($checkpoint) ? $checkpoint : null,
                    root: $root,
                );
            })
            ->values()
            ->all();

        return [
            'schema' => 'x-change.claim-experience-preview.journey.v1',
            'step_count' => count($steps),
            'steps' => $steps,
        ];
    }

    /**
     * @param  array<string, mixed>  $scenario
     * @return array<int, array<string, mixed>>
     */
    private function canonicalSteps(array $scenario): array
    {
        return collect(data_get($scenario, 'checkpoints', []))
            ->filter(
                fn (mixed $checkpoint): bool => is_array($checkpoint)
                    && ($checkpoint['actor'] ?? 'redeemer') === 'redeemer'
            )
            ->map(function (array $checkpoint): array {
                $key = $this->canonicalKey((string) ($checkpoint['key'] ?? ''));

                return [
                    'key' => $key,
                    'phase' => $this->phase($key),
                    'title' => (string) ($checkpoint['title'] ?? 'Claim step'),
                    'description' => (string) ($checkpoint['expected'] ?? ''),
                    'actor' => 'redeemer',
                ];
            })
            ->filter(fn (array $step): bool => $step['key'] !== '')
            ->unique('key')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $step
     * @param  array<string, mixed>|null  $checkpoint
     * @return array<string, mixed>
     */
    private function step(
        array $step,
        int $sequence,
        ?array $checkpoint,
        ?string $root,
    ): array {
        $frame = $checkpoint === null
            ? null
            : $this->frame($checkpoint, $root);

        return [
            ...$step,
            'sequence' => $sequence,
            'render_kind' => $frame === null ? 'experience_card' : 'captured_frame',
            'status' => $frame === null ? 'pending_capture' : 'captured',
            'frame' => $frame,
        ];
    }

    /**
     * @param  array<string, mixed>  $checkpoint
     * @return array<string, mixed>|null
     */
    private function frame(array $checkpoint, ?string $root): ?array
    {
        $path = $checkpoint['screenshot_path'] ?? null;

        if (! is_string($path) || $root === null || ! $this->files->isFile($path)) {
            return null;
        }

        $root = rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        $realRoot = realpath($root);
        $realPath = realpath($path);

        if (
            ! is_string($realRoot)
            || ! is_string($realPath)
            || ! str_starts_with($realPath, $realRoot.DIRECTORY_SEPARATOR)
        ) {
            return null;
        }

        $dimensions = @getimagesize($realPath);

        return [
            'artifact' => ltrim(substr($realPath, strlen($realRoot)), DIRECTORY_SEPARATOR),
            'mime_type' => is_array($dimensions) && is_string($dimensions['mime'] ?? null)
                ? $dimensions['mime']
                : 'image/png',
            'sha256' => hash_file('sha256', $realPath) ?: null,
            'width' => is_array($dimensions) ? ($dimensions[0] ?? null) : null,
            'height' => is_array($dimensions) ? ($dimensions[1] ?? null) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    private function storyboard(array $report): array
    {
        $path = data_get($report, 'artifacts.storyboard_json');

        if (! is_string($path) || ! $this->files->isFile($path)) {
            return [];
        }

        $decoded = json_decode($this->files->get($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function artifactRoot(array $report): ?string
    {
        $root = data_get($report, 'artifacts.root');

        return is_string($root) && $root !== '' ? $root : null;
    }

    private function canonicalKey(string $key): string
    {
        return match ($key) {
            'claim-entry-empty' => 'claim-entry',
            default => $key,
        };
    }

    private function phase(string $key): string
    {
        return match (true) {
            in_array($key, ['claim-entry', 'xray-preview', 'named-slice-selection'], true) => 'entry',
            str_contains($key, 'splash') => 'introduction',
            str_contains($key, 'payout-form'), str_contains($key, 'claim-details') => 'inputs',
            str_starts_with($key, 'validation-') => 'validation',
            $key === 'confirmation' => 'review',
            str_contains($key, 'approval') => 'approval',
            str_contains($key, 'success') => 'completion',
            str_contains($key, 'redirect'), $key === 'rider-url' => 'handoff',
            default => 'claim',
        };
    }
}
