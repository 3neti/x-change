<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

final class CommercialRecipientDesignation extends Model
{
    protected $table = 'x_change_commercial_recipient_designations';

    protected $fillable = [
        'designation_reference', 'counterparty_reference', 'commercial_role',
        'component_scope', 'agreement_reference', 'settlement_designation_reference',
        'tax_profile_reference', 'origin', 'authority_reference', 'authority_hash',
        'source_reference', 'representative_type', 'representative_reference',
        'accepted_snapshot_hash', 'acceptance_evidence_hash',
        'activated_by_type', 'activated_by_id', 'effective_from', 'effective_until',
        'activated_at', 'revocation_reference', 'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'component_scope' => 'array',
            'effective_from' => 'immutable_datetime',
            'effective_until' => 'immutable_datetime',
            'activated_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    public function activatedBy(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeCurrentlyEffective(Builder $query): Builder
    {
        return $query
            ->whereNull('revoked_at')
            ->where('effective_from', '<=', now())
            ->where(function (Builder $query): void {
                $query->whereNull('effective_until')->orWhere('effective_until', '>', now());
            });
    }
}
