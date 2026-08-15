<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

final class TreasuryOperatorAuthorization extends Model
{
    protected $table = 'x_change_treasury_operator_authorizations';

    protected $fillable = [
        'operator_type', 'operator_id', 'capability', 'authorization_reference',
        'granted_by_type', 'granted_by_id', 'valid_from', 'valid_until', 'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'valid_from' => 'immutable_datetime',
            'valid_until' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    public function operator(): MorphTo
    {
        return $this->morphTo();
    }

    public function grantedBy(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeCurrentlyValid(Builder $query): Builder
    {
        return $query->where('valid_from', '<=', now())
            ->whereNull('revoked_at')
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('valid_until')
                ->orWhere('valid_until', '>', now()));
    }
}
