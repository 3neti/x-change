<?php

declare(strict_types=1);

namespace LBHurtado\XChange\ClaimWalkthrough;

use Illuminate\Routing\UrlGenerator;
use LBHurtado\XChange\Models\ClaimPreviewArtifact;

final class ClaimPreviewWebManifestPresenter
{
    public function __construct(
        private readonly UrlGenerator $urls,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function present(
        ClaimPreviewArtifact $artifact,
        bool $cacheHit = false,
    ): array {
        $steps = collect(data_get($artifact->metadata, 'journey.steps', []))
            ->filter(fn (mixed $step): bool => is_array($step))
            ->values()
            ->map(fn (array $step): array => $this->step($artifact, $step))
            ->all();

        return [
            'schema' => 'x-change.claim-experience-preview.manifest.v1',
            'status' => 'ready',
            'reference' => $artifact->reference,
            'fingerprint' => $artifact->artifact_fingerprint,
            'generated_at' => $artifact->generated_at?->toIso8601String(),
            'cache_hit' => $cacheHit,
            'safety' => [
                'preview_only' => true,
                'interactive' => false,
                'money_movement' => false,
                'provider_calls' => false,
                'claim_submission' => false,
            ],
            'journey' => [
                'step_count' => count($steps),
                'steps' => $steps,
            ],
            'exports' => [
                'pdf_url' => $this->exportUrl($artifact, 'pdf'),
                'html_url' => $this->exportUrl($artifact, 'html'),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $step
     * @return array<string, mixed>
     */
    private function step(
        ClaimPreviewArtifact $artifact,
        array $step,
    ): array {
        $key = (string) ($step['key'] ?? '');
        $frame = is_array($step['frame'] ?? null)
            ? $step['frame']
            : null;
        $screen = is_array($step['screen'] ?? null)
            ? $step['screen']
            : null;

        return [
            'sequence' => (int) ($step['sequence'] ?? 0),
            'key' => $key,
            'phase' => (string) ($step['phase'] ?? 'claim'),
            'title' => (string) ($step['title'] ?? 'Claim step'),
            'description' => (string) ($step['description'] ?? ''),
            'actor' => 'redeemer',
            'render_kind' => (string) ($step['render_kind'] ?? 'live_screen'),
            'status' => (string) ($step['status'] ?? 'rendered'),
            'screen' => $screen,
            'frame' => $frame === null ? null : [
                'url' => $this->urls->route(
                    'x-change.cockpit.quick-generate.claim-previews.frames.show',
                    [
                        'claimPreviewArtifact' => $artifact->reference,
                        'step' => $key,
                    ],
                    false,
                ),
                'mime_type' => (string) ($frame['mime_type'] ?? 'image/png'),
                'sha256' => is_string($frame['sha256'] ?? null)
                    ? $frame['sha256']
                    : null,
                'width' => is_numeric($frame['width'] ?? null)
                    ? (int) $frame['width']
                    : null,
                'height' => is_numeric($frame['height'] ?? null)
                    ? (int) $frame['height']
                    : null,
            ],
        ];
    }

    private function exportUrl(
        ClaimPreviewArtifact $artifact,
        string $format,
    ): string {
        return $this->urls->route(
            'x-change.cockpit.quick-generate.claim-previews.exports.show',
            [
                'claimPreviewArtifact' => $artifact->reference,
                'format' => $format,
            ],
            false,
        );
    }
}
