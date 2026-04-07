<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data;

use Spatie\LaravelData\Data;

class PayCodeLinksData extends Data
{
    public function __construct(
        public string $redeem,
        public string $redeem_path,
    ) {}
}
