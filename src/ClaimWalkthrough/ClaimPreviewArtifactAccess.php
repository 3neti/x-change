<?php

declare(strict_types=1);

namespace LBHurtado\XChange\ClaimWalkthrough;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Filesystem\Filesystem;
use LBHurtado\XChange\Models\ClaimPreviewArtifact;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ClaimPreviewArtifactAccess
{
    public function __construct(
        private readonly Filesystem $files,
    ) {}

    public function assertReadable(
        ClaimPreviewArtifact $artifact,
        ?Authenticatable $owner,
    ): void {
        if (
            $owner === null
            || ! $artifact->isOwnedBy($owner)
            || $artifact->status !== 'ready'
            || ($artifact->expires_at !== null && $artifact->expires_at->isPast())
        ) {
            throw new NotFoundHttpException;
        }
    }

    public function framePath(
        ClaimPreviewArtifact $artifact,
        string $step,
    ): string {
        $frame = collect(data_get($artifact->metadata, 'journey.steps', []))
            ->first(fn (mixed $candidate): bool => is_array($candidate)
                && ($candidate['key'] ?? null) === $step);
        $relativePath = is_array($frame)
            ? data_get($frame, 'frame.artifact')
            : null;

        if (! is_string($relativePath) || $relativePath === '') {
            throw new NotFoundHttpException;
        }

        return $this->resolve($artifact, $relativePath);
    }

    /**
     * @return array<string, mixed>
     */
    public function step(
        ClaimPreviewArtifact $artifact,
        string $step,
    ): array {
        $candidate = collect(data_get($artifact->metadata, 'journey.steps', []))
            ->first(fn (mixed $candidate): bool => is_array($candidate)
                && ($candidate['key'] ?? null) === $step);

        if (! is_array($candidate)) {
            throw new NotFoundHttpException;
        }

        return $candidate;
    }

    public function exportPath(
        ClaimPreviewArtifact $artifact,
        string $format,
    ): string {
        $relativePath = match ($format) {
            'pdf' => 'walkthrough-storyboard.pdf',
            'html' => 'walkthrough-storyboard.html',
            default => throw new NotFoundHttpException,
        };

        return $this->resolve($artifact, $relativePath);
    }

    private function resolve(
        ClaimPreviewArtifact $artifact,
        string $relativePath,
    ): string {
        $root = realpath(storage_path('app/'.$artifact->artifact_path));
        $path = realpath(storage_path(
            'app/'.$artifact->artifact_path.'/'.ltrim($relativePath, '/')
        ));

        if (
            ! is_string($root)
            || ! is_string($path)
            || ! str_starts_with($path, $root.DIRECTORY_SEPARATOR)
            || ! $this->files->isFile($path)
        ) {
            throw new NotFoundHttpException;
        }

        return $path;
    }
}
