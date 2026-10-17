<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Claim;

final readonly class RiderStampCopyData
{
    public function __construct(
        public string $source,
        public string $title,
        public string $description,
        public string $rasterDescription,
        public bool $visible,
    ) {}

    public function plainText(): string
    {
        if (! $this->visible) {
            return '';
        }
        if ($this->title === 'Pay Code' && $this->description === 'Money should adapt to people. Not the other way around.') {
            return "Money should adapt to people.\nNot the other way around.";
        }

        return trim($this->title."\n".$this->description);
    }
}
