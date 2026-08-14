<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\LinkPreview\Canonicalizers;

use LBHurtado\XChange\Services\LinkPreview\LinkPreviewDriverData;

final class StripQueryCanonicalizer implements LinkCanonicalizer
{
    public function canonicalize(string $url, LinkPreviewDriverData $driver): ?string
    {
        $parts = parse_url(trim($url));

        if (! is_array($parts) || ! $driver->matches($parts)) {
            return null;
        }

        $host = strtolower((string) $parts['host']);
        $path = '/'.ltrim((string) ($parts['path'] ?? ''), '/');

        return 'https://'.$host.rtrim($path, '/');
    }
}
