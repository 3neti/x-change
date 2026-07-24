<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LBHurtado\XChange\Enums\PaymentAttemptStatus;

class PaymentAttemptEvent extends Model
{
    protected $table = 'x_change_payment_attempt_events';

    protected $fillable = [
        'sequence',
        'event_type',
        'from_status',
        'to_status',
        'trigger',
        'evidence_reference',
        'metadata',
        'occurred_at',
    ];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new \LogicException('Payment Attempt events are append-only.');
        });

        static::deleting(function (): never {
            throw new \LogicException('Payment Attempt events are append-only.');
        });
    }

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'from_status' => PaymentAttemptStatus::class,
            'to_status' => PaymentAttemptStatus::class,
            'metadata' => 'array',
            'occurred_at' => 'immutable_datetime',
        ];
    }

    public function paymentAttempt(): BelongsTo
    {
        return $this->belongsTo(PaymentAttempt::class);
    }
}
