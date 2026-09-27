<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Legal;

final readonly class AgreementDocumentData
{
    public function __construct(
        public string $key,
        public string $version,
        public string $title,
        public ?string $effectiveAt,
        public string $sha256,
        public string $markdown,
        public string $html,
    ) {}
}
