<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

final class CommercialOperatorAuthorization extends Model
{
    protected $table = 'x_change_commercial_operator_authorizations';

    protected $guarded = [];

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
        return $query
            ->where('valid_from', '<=', now())
            ->whereNull('revoked_at')
            ->where(function (Builder $query): void {
                $query->whereNull('valid_until')->orWhere('valid_until', '>', now());
            });
    }
}
