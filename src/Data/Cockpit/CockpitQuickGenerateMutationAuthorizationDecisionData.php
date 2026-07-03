<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Cockpit;

use Spatie\LaravelData\Data;

class CockpitQuickGenerateMutationAuthorizationDecisionData extends Data
{
    /**
     * @param  array<string, mixed>  $redactions
     */
    public function __construct(
        public readonly string $status = 'not_wired',
        public readonly string $decision = 'not-loaded',
        public readonly string $required_approval = 'not-loaded',
        public readonly string $rationale = 'not-loaded',
        public readonly string $next_step = 'not-loaded',
        public readonly array $redactions = ['payloads' => 'not-loaded'],
    ) {}
}
