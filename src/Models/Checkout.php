<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use LBHurtado\Contact\Models\Contact;

final class Checkout extends Model
{
    protected $table = 'x_change_checkouts';

    protected $guarded = [];

    protected $hidden = ['visitor_mobile_ciphertext', 'visitor_mobile_hash', 'instructions_ciphertext', 'pricing_snapshot_ciphertext', 'guest_token_hash', 'session_hash'];

    protected static function booted(): void
    {
        self::creating(function (self $checkout): void {
            $checkout->reference ??= (string) Str::ulid();
        });
    }

    protected function casts(): array
    {
        return [
            'visitor_mobile_ciphertext' => 'encrypted',
            'instructions_ciphertext' => 'encrypted:array',
            'pricing_snapshot_ciphertext' => 'encrypted:array',
            'placed_at' => 'immutable_datetime',
            'draft_expires_at' => 'immutable_datetime',
        ];
    }

    public function fundingOrder(): BelongsTo
    {
        return $this->belongsTo(PayCodeIssuanceFundingOrder::class, 'funding_order_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(CheckoutEvent::class)->orderBy('id');
    }

    public function refundCase(): HasOne
    {
        return $this->hasOne(CheckoutRefundCase::class);
    }
}
