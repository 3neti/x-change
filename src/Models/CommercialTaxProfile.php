<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\XChange\Casts\UtcImmutableDateTime;

final class CommercialTaxProfile extends Model
{
    protected $table = 'x_change_commercial_tax_profiles';

    protected $fillable = [
        'reference', 'version', 'jurisdiction', 'currency', 'tax_type',
        'calculation_basis', 'rate_basis_points', 'rounding_method',
        'rounding_scope', 'collection_method', 'tax_recipient_reference',
        'effective_from', 'effective_until', 'snapshot_hash', 'snapshot',
    ];

    protected static function booted(): void
    {
        self::updating(function (): never {
            throw new \LogicException('Commercial Tax Profiles are immutable.');
        });

        self::deleting(function (): never {
            throw new \LogicException('Commercial Tax Profiles cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'rate_basis_points' => 'integer',
            'effective_from' => UtcImmutableDateTime::class,
            'effective_until' => UtcImmutableDateTime::class,
            'snapshot' => 'array',
        ];
    }

    public function scopeCurrentlyEffective(Builder $query): Builder
    {
        return $query
            ->where('effective_from', '<=', now())
            ->where(function (Builder $query): void {
                $query->whereNull('effective_until')->orWhere('effective_until', '>', now());
            });
    }
}
