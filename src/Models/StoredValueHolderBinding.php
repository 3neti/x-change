<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Casts\UtcImmutableDateTime;
use LogicException;

final class StoredValueHolderBinding extends Model
{
    protected $table = 'x_change_stored_value_holder_bindings';

    protected $fillable = [
        'reference',
        'voucher_id',
        'allocation_reference',
        'reservation_operation_reference',
        'activation_operation_reference',
        'holder_type',
        'holder_id',
        'holder_principal_reference',
        'holder_authority_reference',
        'currency',
        'status',
        'activated_at',
        'released_at',
    ];

    protected $attributes = [
        'status' => 'active',
    ];

    protected $hidden = [
        'holder_type',
        'holder_id',
        'holder_principal_reference',
        'holder_authority_reference',
        'reservation_operation_reference',
        'activation_operation_reference',
    ];

    protected static function booted(): void
    {
        self::creating(function (self $binding): void {
            $binding->reference ??= (string) Str::ulid();
        });

        self::updating(function (): never {
            throw new LogicException(
                'Stored value holder bindings must be changed through guarded lifecycle actions.',
            );
        });

        self::deleting(function (): never {
            throw new LogicException('Stored value holder bindings cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'voucher_id' => 'integer',
            'activated_at' => UtcImmutableDateTime::class,
            'released_at' => UtcImmutableDateTime::class,
        ];
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function holder(): MorphTo
    {
        return $this->morphTo();
    }
}
