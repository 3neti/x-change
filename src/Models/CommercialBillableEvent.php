<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LBHurtado\XChange\Enums\CommercialBillableEventStatus;

final class CommercialBillableEvent extends Model
{
    protected $table = 'x_change_commercial_billable_events';

    protected $fillable = [
        'commercial_sale_id',
        'commercial_recognition_policy_id',
        'event_reference',
        'event_type',
        'recognition_policy_reference',
        'recognition_policy_version',
        'recognition_policy_hash',
        'recognition_policy_snapshot',
        'source_event_reference',
        'component_reference',
        'quantity',
        'unit_amount_minor',
        'total_amount_minor',
        'currency',
        'payload_hash',
        'status',
        'reversal_reference',
        'received_at',
        'posted_at',
        'reversed_at',
    ];

    protected $attributes = [
        'status' => 'received',
    ];

    protected static function booted(): void
    {
        self::updating(function (): never {
            throw new \LogicException('Commercial Billable Events must be changed through guarded commercial actions.');
        });

        self::deleting(function (): never {
            throw new \LogicException('Commercial Billable Events cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'commercial_recognition_policy_id' => 'integer',
            'quantity' => 'integer',
            'recognition_policy_version' => 'integer',
            'recognition_policy_snapshot' => 'array',
            'unit_amount_minor' => 'integer',
            'total_amount_minor' => 'integer',
            'status' => CommercialBillableEventStatus::class,
            'received_at' => 'immutable_datetime',
            'posted_at' => 'immutable_datetime',
            'reversed_at' => 'immutable_datetime',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(CommercialSale::class, 'commercial_sale_id');
    }

    public function recognitionPolicy(): BelongsTo
    {
        return $this->belongsTo(CommercialRecognitionPolicy::class, 'commercial_recognition_policy_id');
    }
}
