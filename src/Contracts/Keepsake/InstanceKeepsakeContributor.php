<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts\Keepsake;

use LBHurtado\XChange\Data\Keepsake\InstanceKeepsakeContext;
use LBHurtado\XChange\Data\Keepsake\InstanceKeepsakeContribution;

interface InstanceKeepsakeContributor
{
    public function key(): string;

    public function snapshotSchemaVersion(): int;

    public function blueprintSchemaVersion(): ?int;

    public function contribute(InstanceKeepsakeContext $context): InstanceKeepsakeContribution;
}
