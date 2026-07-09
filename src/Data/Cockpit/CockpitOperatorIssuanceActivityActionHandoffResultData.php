<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Cockpit;

use Spatie\LaravelData\Data;

class CockpitOperatorIssuanceActivityActionHandoffResultData extends Data
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $schema = 'x-change.cockpit.operator-issuance-activity-action-handoff.v1',
        public readonly string $status = 'not_wired',
        public readonly ?string $activity_id = null,
        public readonly ?string $correlation_id = null,
        public readonly ?string $action_hint_id = null,
        public readonly ?string $action_run_id = null,
        public readonly bool $action_required = false,
        public readonly bool $executes_action = false,
        public readonly string $source = 'null-cockpit-operator-issuance-activity-action-handoff',
        public readonly string $reason = 'x-action handoff is not wired. Cockpit does not execute workflow actions in this boundary.',
        public readonly array $metadata = [],
    ) {}
}
