<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ObservedPaymentStatus extends Model
{
    protected $table = 'x_change_observed_payment_statuses';

    protected $fillable = ['observed_payment_transaction_id', 'provider_status', 'observed_at'];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new \LogicException('Observed payment statuses are append-only.');
        });

        static::deleting(function (): never {
            throw new \LogicException('Observed payment statuses cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return ['observed_at' => 'immutable_datetime'];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(ObservedPaymentTransaction::class, 'observed_payment_transaction_id');
    }
}
