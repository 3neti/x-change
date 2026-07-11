<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Cockpit;

use Spatie\LaravelData\Data;

class CockpitOperatorIssuanceActivityRuntimeProfileData extends Data
{
    /**
     * @param  array<int, array<string, mixed>>  $components
     * @param  array<string, mixed>  $safety
     */
    public function __construct(
        public readonly string $schema = 'x-change.cockpit.operator-issuance-activity-runtime-profile.v1',
        public readonly string $status = 'not_wired',
        public readonly bool $repository_enabled = false,
        public readonly bool $recorder_enabled = false,
        public readonly bool $journal_handoff_enabled = false,
        public readonly bool $action_handoff_enabled = false,
        public readonly bool $feedback_handoff_enabled = false,
        public readonly array $components = [],
        public readonly array $safety = [],
    ) {}
}
