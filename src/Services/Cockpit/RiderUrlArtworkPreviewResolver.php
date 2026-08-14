<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use LBHurtado\XChange\Services\LinkPreview\LinkPreviewEngine;

final class RiderUrlArtworkPreviewResolver
{
    public function __construct(
        private readonly LinkPreviewEngine $engine,
    ) {}

    /**
     * @return array{
     *     available: bool,
     *     source: string,
     *     title: string,
     *     description: string,
     *     image_url: ?string,
     *     public_image_url: ?string,
     *     reference: string
     * }
     */
    public function resolve(string $url): array
    {
        return $this->engine->resolve($url);
    }
}
