<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;
use LBHurtado\XChange\Casts\UtcImmutableDateTime;

final class StandingFundingAddressBindingEffectiveTimeCorrection extends Model
{
    protected $table = 'x_change_standing_funding_address_binding_effective_time_corrections';

    protected $fillable = [
        'reference', 'standing_funding_address_binding_revision_id',
        'standing_funding_address_binding_migration_id', 'original_effective_at',
        'corrected_effective_at', 'approved_evidence_hash', 'correction_hash',
        'idempotency_key_hash', 'authorization_reference', 'corrected_by_type',
        'corrected_by_id', 'reason',
    ];

    protected $hidden = ['idempotency_key_hash'];

    protected static function booted(): void
    {
        self::creating(function (self $correction): void {
            $correction->reference ??= (string) Str::ulid();
        });

        self::updating(function (): never {
            throw new \LogicException('Binding effective-time corrections are immutable.');
        });

        self::deleting(function (): never {
            throw new \LogicException('Binding effective-time corrections cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'original_effective_at' => UtcImmutableDateTime::class,
            'corrected_effective_at' => UtcImmutableDateTime::class,
        ];
    }

    public function bindingRevision(): BelongsTo
    {
        return $this->belongsTo(
            StandingFundingAddressBindingRevision::class,
            'standing_funding_address_binding_revision_id',
        );
    }

    public function bindingMigration(): BelongsTo
    {
        return $this->belongsTo(
            StandingFundingAddressBindingMigration::class,
            'standing_funding_address_binding_migration_id',
        );
    }

    public function correctedBy(): MorphTo
    {
        return $this->morphTo();
    }
}
