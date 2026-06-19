<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Claim;

use Spatie\LaravelData\Data;

class ClaimPhaseData extends Data
{
    public function __construct(
        public string $key,
        public string $owner,
        public string $source,
        public string $status = 'active',
        public array $stages = [],
        public array $fields = [],
        public ?string $url = null,
        public ?string $action_url = null,
        public array $skip_stages = [],
        public ?int $delay_seconds = null,
        public bool $show_countdown = false,
        public array $meta = [],
    ) {}
}
