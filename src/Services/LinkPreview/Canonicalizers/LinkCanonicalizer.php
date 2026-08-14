<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\LinkPreview\Canonicalizers;

use LBHurtado\XChange\Services\LinkPreview\LinkPreviewDriverData;

interface LinkCanonicalizer
{
    public function canonicalize(string $url, LinkPreviewDriverData $driver): ?string;
}
