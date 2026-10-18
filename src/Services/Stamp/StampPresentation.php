<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Stamp;

use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use LBHurtado\XStamp\Appearance;
use LBHurtado\XStamp\Design;

final class StampPresentation
{
    /** @param array<string, mixed>|null $candidate @return array<string, mixed>|null */
    public function snapshot(Design $design, ?array $candidate = null): ?array
    {
        try {
            if (! isset($design->document['customization'])) {
                if ($candidate !== null && $candidate !== []) {
                    throw new InvalidArgumentException('The saved design does not support these appearance settings.');
                }

                return null;
            }
            $candidate ??= [];
            if (array_diff(array_keys($candidate), ['schema', 'design_id', 'revision', 'options', 'resolved']) !== []
                || ! is_array($candidate['options'] ?? [])
                || (isset($candidate['schema']) && $candidate['schema'] !== 'x-stamp-presentation/1')
                || (isset($candidate['design_id']) && $candidate['design_id'] !== $design->id())
                || (isset($candidate['revision']) && $candidate['revision'] !== $design->revision())
                || (isset($candidate['resolved']) && ! is_array($candidate['resolved']))) {
                throw new InvalidArgumentException('Invalid or mismatched Stamp presentation snapshot.');
            }
            $resolved = (new Appearance)->resolve($design, $candidate['options'] ?? [], (array) config('x-change.experience.stamp_presentation', []), $candidate['resolved'] ?? null);

            return ['schema' => 'x-stamp-presentation/1', 'design_id' => $design->id(), 'revision' => $design->revision(), 'options' => $resolved['options'], 'resolved' => $resolved['qr']];
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['metadata.custom.stamp_presentation' => $exception->getMessage()]);
        }
    }

    /** @param array<string, mixed>|null $snapshot @return array{options: array<string, mixed>, qr: array<string, float|int>} */
    public function appearance(Design $design, ?array $snapshot = null): array
    {
        $snapshot = $this->snapshot($design, $snapshot);

        return $snapshot === null ? (new Appearance)->resolve($design) : ['options' => $snapshot['options'], 'qr' => $snapshot['resolved']];
    }
}
