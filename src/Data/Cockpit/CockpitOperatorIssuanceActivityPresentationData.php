<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Cockpit;

use Spatie\LaravelData\Data;

class CockpitOperatorIssuanceActivityPresentationData extends Data
{
    /**
     * @param  array{journal: string, action: string, feedback: string}  $handoffs
     * @param  array{presentation_only: bool, writes_journal: bool, executes_actions: bool, sends_feedback: bool, moves_money: bool, owns_lifecycle_truth: bool}  $safety
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $schema = 'x-change.cockpit.operator-issuance-activity-presentation.v1',
        public readonly string $id = '',
        public readonly string $code = '',
        public readonly string $title = '',
        public readonly string $subtitle = '',
        public readonly string $status = '',
        public readonly ?string $detail_href = null,
        public readonly ?string $correlation_id = null,
        public readonly array $handoffs = [
            'journal' => 'not_wired',
            'action' => 'not_wired',
            'feedback' => 'not_wired',
        ],
        public readonly array $safety = [
            'presentation_only' => true,
            'writes_journal' => false,
            'executes_actions' => false,
            'sends_feedback' => false,
            'moves_money' => false,
            'owns_lifecycle_truth' => false,
        ],
        public readonly array $metadata = [],
    ) {}
}
