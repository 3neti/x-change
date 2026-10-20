<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Claim;

final readonly class ClaimShareMetadataData
{
    public function __construct(
        public string $title,
        public string $description,
        public string $url,
        public string $siteName,
        public ?string $imageUrl,
        public string $imageAlt,
        public int $imageWidth = 1200,
        public int $imageHeight = 630,
    ) {}

    /**
     * @return array{
     *     title: string,
     *     description: string,
     *     url: string,
     *     site_name: string,
     *     image_url: ?string,
     *     image_alt: string
     * }
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'url' => $this->url,
            'site_name' => $this->siteName,
            'image_url' => $this->imageUrl,
            'image_alt' => $this->imageAlt,
            'image_width' => $this->imageWidth,
            'image_height' => $this->imageHeight,
        ];
    }
}
