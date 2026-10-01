<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data;

use LBHurtado\XChange\Enums\QrArtifactKind;
use Spatie\LaravelData\Data;

final class QrArtifactData extends Data
{
    public function __construct(
        public QrArtifactKind $kind,
        public string $destination,
        public string $image_data_uri,
        public string $title,
        public string $description,
        public ?string $identifier,
        public string $center_mark = 'pay_code',
    ) {}
}
