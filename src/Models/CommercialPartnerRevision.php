<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LBHurtado\XChange\Enums\CommercialPartnerRevisionStatus;
use LogicException;

final class CommercialPartnerRevision extends Model
{
    protected $table = 'x_change_commercial_partner_revisions';

    protected $fillable = [
        'commercial_partner_id', 'version', 'status', 'display_name', 'legal_name',
        'external_reference', 'attribution_basis', 'authorization_reference', 'terms',
        'snapshot_hash', 'maker_type', 'maker_id', 'checker_type', 'checker_id',
        'submitted_at', 'approved_at', 'effective_at', 'superseded_at',
    ];

    protected $attributes = [
        'status' => 'draft',
        'terms' => '[]',
    ];

    protected static function booted(): void
    {
        self::updating(function (self $revision): void {
            $mutableWorkflowFields = [
                'status', 'checker_type', 'checker_id', 'submitted_at', 'approved_at',
                'effective_at', 'superseded_at',
            ];

            if (array_diff(array_keys($revision->getDirty()), $mutableWorkflowFields) !== []) {
                throw new LogicException('Commercial Partner revision content is immutable.');
            }
        });

        self::deleting(function (): never {
            throw new LogicException('Commercial Partner revisions cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'status' => CommercialPartnerRevisionStatus::class,
            'terms' => 'array',
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

    public function maker(): MorphTo
    {
        return $this->morphTo();
    }

    public function checker(): MorphTo
    {
        return $this->morphTo();
    }
}
