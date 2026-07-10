<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;

class CockpitOperatorIssuanceActivity extends Model
{
    protected $table = 'x_change_cockpit_operator_issuance_activities';

    protected $fillable = [
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
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'retention_until' => 'datetime',
            'safe_context' => 'array',
            'redaction_flags' => 'array',
            'metadata' => 'array',
        ];
    }
}
