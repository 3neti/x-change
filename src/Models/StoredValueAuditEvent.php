<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class StoredValueAuditEvent extends Model
{
    protected $table = 'x_change_stored_value_audit_events';

    protected $fillable = [
        'event_id',
        'stored_value_holder_binding_id',
        'stored_value_collection_id',
        'event_type',
        'amount_minor',
        'balance_after_minor',
        'currency',
        'occurred_at',
        'journal_status',
        'journal_attempts',
        'journaled_at',
        'journal_error',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'balance_after_minor' => 'integer',
            'journal_attempts' => 'integer',
            'occurred_at' => 'immutable_datetime',
            'journaled_at' => 'immutable_datetime',
        ];
    }

    public function binding(): BelongsTo
    {
        return $this->belongsTo(StoredValueHolderBinding::class, 'stored_value_holder_binding_id');
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(StoredValueCollection::class, 'stored_value_collection_id');
    }
}
