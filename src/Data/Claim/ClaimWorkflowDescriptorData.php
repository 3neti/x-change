<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Claim;

use Spatie\LaravelData\Data;

final class ClaimWorkflowDescriptorData extends Data
{
    /**
     * @param  array<string, scalar|null>  $review
     */
    public function __construct(
        public string $key,
        public bool $requires_mobile,
        public bool $requires_destination,
        public bool $requires_amount,
        public bool $requires_authenticated_officer,
        public string $title,
        public string $description,
        public string $confirmation_label,
        public bool $skip_form_flow_splash = false,
        public array $review = [],
    ) {}
}
