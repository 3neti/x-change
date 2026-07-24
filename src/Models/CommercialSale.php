<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class CommercialSale extends Model
{
    protected $table = 'x_change_commercial_sales';

    protected $guarded = [];

    protected static function booted(): void
    {
        self::updating(function (): never {
            throw new \LogicException('Commercial Sales must be changed through guarded commercial actions.');
        });

        self::deleting(function (): never {
            throw new \LogicException('Commercial Sales cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'catalog_version' => 'integer',
            'waterfall_policy_version' => 'integer',
            'attribution_version' => 'integer',
            'total_price_minor' => 'integer',
            'snapshot' => 'array',
            'accepted_at' => 'immutable_datetime',
            'posted_at' => 'immutable_datetime',
            'reversed_at' => 'immutable_datetime',
        ];
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(CommercialAllocation::class)->orderBy('sequence');
    }
}
