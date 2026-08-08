<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LBHurtado\XChange\Enums\CommercialPartnerRevisionStatus;
use LogicException;

final class CommercialPartnerDestinationRevision extends Model
{
    protected $table = 'x_change_commercial_partner_destination_revisions';

    protected $fillable = [
        'commercial_partner_id', 'commercial_partner_revision_id', 'version', 'status',
        'provider', 'connection_reference', 'currency', 'destination', 'destination_hash',
        'destination_summary', 'maker_type', 'maker_id', 'checker_type', 'checker_id',
        'authorization_reference', 'submitted_at', 'approved_at', 'effective_at', 'superseded_at',
    ];

    protected $attributes = [
        'status' => 'draft',
    ];

    protected static function booted(): void
    {
        self::updating(function (self $revision): void {
            $mutableWorkflowFields = [
                'status', 'checker_type', 'checker_id', 'submitted_at', 'approved_at',
                'effective_at', 'superseded_at',
            ];

            if (array_diff(array_keys($revision->getDirty()), $mutableWorkflowFields) !== []) {
                throw new LogicException('Commercial Partner destination revision content is immutable.');
            }
        });

        self::deleting(function (): never {
            throw new LogicException('Commercial Partner destination revisions cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'status' => CommercialPartnerRevisionStatus::class,
            'destination' => 'encrypted:array',
            'submitted_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
            'effective_at' => 'immutable_datetime',
            'superseded_at' => 'immutable_datetime',
        ];
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', CommercialPartnerRevisionStatus::Approved);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(CommercialPartner::class, 'commercial_partner_id');
    }

    public function partnerRevision(): BelongsTo
    {
        return $this->belongsTo(CommercialPartnerRevision::class, 'commercial_partner_revision_id');
    }

    public function maker(): MorphTo
    {
        return $this->morphTo();
    }

    public function checker(): MorphTo
    {
        return $this->morphTo();
    }
}
