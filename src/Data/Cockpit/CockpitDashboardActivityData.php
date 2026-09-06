<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Cockpit;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class CockpitDashboardActivityData extends Data
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly string $description,
        public readonly string $timestamp,
        public readonly string $source,
        public readonly string|Optional $projection_badge = new Optional,
        public readonly string|Optional $projection_status = new Optional,
        public readonly string|Optional $projection_detail = new Optional,
        public readonly string|Optional $code = new Optional,
        public readonly string|Optional $amount = new Optional,
        public readonly string|Optional $status = new Optional,
        public readonly string|Optional $target_label = new Optional,
        public readonly string|Optional $detail_href = new Optional,
        /**
         * @var array<string, mixed>|Optional
         */
        public readonly array|Optional $claim_summary = new Optional,
        /**
         * @var array<int, string>|Optional
         */
        public readonly array|Optional $projection_targets = new Optional,
        public readonly array|Optional $metadata = new Optional,
    ) {}
}
