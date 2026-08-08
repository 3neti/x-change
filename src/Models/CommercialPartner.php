<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LBHurtado\XChange\Enums\CommercialPartnerStatus;

final class CommercialPartner extends Model
{
    protected $table = 'x_change_commercial_partners';

    protected $fillable = [
        'reference',
        'display_name',
        'status',
        'created_by_type',
        'created_by_id',
        'submitted_at',
        'activated_at',
        'suspended_at',
        'retired_at',
    ];

    protected $attributes = [
        'status' => 'draft',
    ];

    protected function casts(): array
    {
        return [
            'status' => CommercialPartnerStatus::class,
            'submitted_at' => 'immutable_datetime',
            'activated_at' => 'immutable_datetime',
            'suspended_at' => 'immutable_datetime',
            'retired_at' => 'immutable_datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', CommercialPartnerStatus::Active);
    }

    public function createdBy(): MorphTo
    {
        return $this->morphTo();
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(CommercialPartnerRevision::class)->orderByDesc('version');
    }

    public function destinationRevisions(): HasMany
    {
        return $this->hasMany(CommercialPartnerDestinationRevision::class)->orderByDesc('version');
    }

    public function legacyMappings(): HasMany
    {
        return $this->hasMany(CommercialPartnerLegacyMapping::class);
    }
}
