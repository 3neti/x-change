<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

final class CommercialPartnerLegacyMapping extends Model
{
    protected $table = 'x_change_commercial_partner_legacy_mappings';

    protected $fillable = [
        'legacy_partner_reference', 'commercial_partner_id', 'commercial_partner_revision_id',
        'mapped_by_type', 'mapped_by_id', 'authorization_reference', 'mapped_at',
    ];

    protected static function booted(): void
    {
        self::updating(function (): never {
            throw new LogicException('Commercial Partner legacy mappings are immutable.');
        });

        self::deleting(function (): never {
            throw new LogicException('Commercial Partner legacy mappings cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return ['mapped_at' => 'immutable_datetime'];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(CommercialPartner::class, 'commercial_partner_id');
    }

    public function partnerRevision(): BelongsTo
    {
        return $this->belongsTo(CommercialPartnerRevision::class, 'commercial_partner_revision_id');
    }

    public function mappedBy(): MorphTo
    {
        return $this->morphTo();
    }
}
