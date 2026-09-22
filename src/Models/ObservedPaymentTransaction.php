<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ObservedPaymentTransaction extends Model
{
    protected $table = 'x_change_observed_payment_transactions';

    protected $fillable = [
        'payment_attempt_id', 'provider_code', 'provider_transaction_hash',
        'provider_transaction_id_ciphertext', 'amount_minor', 'currency',
        'provider_status', 'settlement_rail', 'payer_name_ciphertext',
        'payer_account_ciphertext', 'payer_institution_ciphertext',
        'payer_mobile_ciphertext', 'occurred_at', 'settled_at', 'observed_at',
    ];

    protected $hidden = [
        'provider_transaction_hash', 'provider_transaction_id_ciphertext',
        'payer_name_ciphertext', 'payer_account_ciphertext',
        'payer_institution_ciphertext', 'payer_mobile_ciphertext',
    ];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new \LogicException('Observed payment transactions are append-only.');
        });

        static::deleting(function (): never {
            throw new \LogicException('Observed payment transactions cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'provider_transaction_id_ciphertext' => 'encrypted',
            'payer_name_ciphertext' => 'encrypted',
            'payer_account_ciphertext' => 'encrypted',
            'payer_institution_ciphertext' => 'encrypted',
            'payer_mobile_ciphertext' => 'encrypted',
            'occurred_at' => 'immutable_datetime',
            'settled_at' => 'immutable_datetime',
            'observed_at' => 'immutable_datetime',
        ];
    }

    public function paymentAttempt(): BelongsTo
    {
        return $this->belongsTo(PaymentAttempt::class);
    }

    public function statuses(): HasMany
    {
        return $this->hasMany(ObservedPaymentStatus::class)->orderBy('id');
    }
}
