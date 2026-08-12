<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Claim;

use LBHurtado\XChange\Data\Claim\ClaimSurfaceActionData;
use LBHurtado\XChange\Data\Claim\ClaimSurfaceComponentData;
use LBHurtado\XChange\Data\Claim\ClaimSurfaceData;
use LBHurtado\XChange\Data\Claim\ClaimSurfaceStateData;
use LBHurtado\XChange\Data\Claim\ClaimSurfaceViewerData;

final class ClaimSurfaceBuilder
{
    private string $visibility = 'public_preview';

    private string $headline = '';

    private ?string $description = null;

    /** @var array<string, mixed> */
    private array $facts = [];

    /** @var list<ClaimSurfaceComponentData> */
    private array $components = [];

    /** @var list<ClaimSurfaceActionData> */
    private array $actions = [];

    /** @var list<string> */
    private array $warnings = [];

    public function setVisibility(string $visibility): self
    {
        $this->visibility = $visibility;

        return $this;
    }

    public function visibility(): string
    {
        return $this->visibility;
    }

    public function setHeadline(string $headline): self
    {
        $this->headline = $headline;

        return $this;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function addFact(string $key, mixed $value): self
    {
        $this->facts[$key] = $value;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $props
     */
    public function addComponent(string $type, array $props = []): self
    {
        $this->components[] = new ClaimSurfaceComponentData($type, $props);

        return $this;
    }

    public function hasComponent(string $type): bool
    {
        foreach ($this->components as $component) {
            if ($component->type === $type) {
                return true;
            }
        }

        return false;
    }

    public function addAction(
        string $key,
        string $label,
        ?string $href = null,
        string $method = 'get',
        string $variant = 'secondary',
    ): self {
        $this->actions[] = new ClaimSurfaceActionData($key, $label, $href, $method, $variant);

        return $this;
    }

    public function addWarning(string $warning): self
    {
        $this->warnings[] = $warning;

        return $this;
    }

    public function build(
        string $code,
        ClaimSurfaceViewerData $viewer,
        ClaimSurfaceStateData $state,
    ): ClaimSurfaceData {
        return new ClaimSurfaceData(
            code: $code,
            viewer: $viewer,
            state: $state,
            visibility: $this->visibility,
            headline: $this->headline,
            description: $this->description,
            facts: $this->facts,
            components: array_map(
                static fn (ClaimSurfaceComponentData $component): array => $component->toArray(),
                $this->components,
            ),
            actions: array_map(
                static fn (ClaimSurfaceActionData $action): array => $action->toArray(),
                $this->actions,
            ),
            warnings: $this->warnings,
        );
    }
}
