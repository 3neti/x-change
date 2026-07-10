<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('creates the cockpit operator issuance activities table with expected columns', function () {
    expect(Schema::hasTable('x_change_cockpit_operator_issuance_activities'))->toBeTrue();

    expect(Schema::getColumnListing('x_change_cockpit_operator_issuance_activities'))->toEqualCanonicalizing([
        'id',
        'activity_id',
        'schema',
        'actor_id',
        'actor_label',
        'source',
        'subject_type',
        'subject_reference',
        'status',
        'severity',
        'occurred_at',
        'idempotency_key_hash',
        'correlation_id',
        'causation_id',
        'summary',
        'safe_context',
        'redaction_flags',
        'journal_handoff_status',
        'action_handoff_status',
        'feedback_handoff_status',
        'retention_until',
        'metadata',
        'created_at',
        'updated_at',
    ]);
});

it('creates the cockpit operator issuance activities indexes required by the read model', function () {
    $indexes = collect(DB::select('PRAGMA index_list(x_change_cockpit_operator_issuance_activities)'))
        ->map(fn (object $index): string => (string) $index->name)
        ->all();

    expect($indexes)->toContain('index_activity_id_unique')
        ->and($indexes)->toContain('index_operator_occurred_at')
        ->and($indexes)->toContain('index_subject_reference')
        ->and($indexes)->toContain('index_correlation_id')
        ->and($indexes)->toContain('index_retention_until');
});
