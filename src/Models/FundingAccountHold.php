<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FundingAccountHold extends Model
{
    protected $table = 'x_change_funding_account_holds';

    protected $fillable = [
        'funding_recovery_id',
        'account_reference',
        'outstanding_amount_minor',
        'currency',
        'status',
        'placed_at',
        'released_at',
        'released_by_type',
        'released_by_id',
        'metadata',
    ];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new \LogicException('Funding Account holds must be changed through guarded recovery actions.');
        });

        static::deleting(function (): never {
            throw new \LogicException('Funding Account holds cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'outstanding_amount_minor' => 'integer',
            'placed_at' => 'immutable_datetime',
            'released_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    public function fundingRecovery(): BelongsTo
    {
        return $this->belongsTo(FundingRecovery::class);
    }
}
