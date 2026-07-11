<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Cockpit;

use Spatie\LaravelData\Data;

class CockpitOperatorIssuanceActivityReadModelData extends Data
{
    /**
     * @param  array<int, CockpitOperatorIssuanceActivityItemData>  $items
     * @param  array<int, CockpitOperatorIssuanceActivityPresentationData>  $presentations
     * @param  array<string, mixed>  $empty_state
     * @param  array<string, mixed>  $search_filters
     * @param  array<string, mixed>  $redactions
     */
    public function __construct(
        public readonly string $schema = 'x-change.cockpit.operator-issuance-activity.v1',
        public readonly string $status = 'not_wired',
        public readonly bool $authorized = false,
        public readonly string $source = 'null-operator-issuance-activity-read-model',
        public readonly array $items = [],
        public readonly array $presentations = [],
        public readonly array $empty_state = [
            'title' => 'No operator issuance activity available',
            'description' => 'Activity recording is not wired yet. Quick Generate can still use the existing issuance path.',
        ],
        public readonly array $search_filters = [
            'schema' => 'x-change.cockpit.operator-issuance-activity-search-filter.v1',
            'status' => 'not_available',
            'read_only' => true,
            'search' => null,
            'statuses' => [],
            'handoff_statuses' => [],
            'available_statuses' => [],
            'available_handoff_statuses' => [],
        ],
        public readonly array $redactions = [
            'payloads' => 'activity-summary-only',
            'raw_payloads_exposed' => false,
            'provider_payloads_exposed' => false,
            'wallet_data_exposed' => false,
            'recipient_secrets_exposed' => false,
            'lifecycle_truth' => false,
            'writes_journal' => false,
            'executes_actions' => false,
            'sends_feedback' => false,
            'moves_money' => false,
        ],
    ) {}
}
