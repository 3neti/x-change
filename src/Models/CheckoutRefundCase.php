<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CheckoutRefundCase extends Model
{
    protected $table = 'x_change_checkout_refund_cases';

    protected $guarded = [];

    protected $hidden = ['external_reference_ciphertext', 'reason'];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'external_reference_ciphertext' => 'encrypted',
            'reason' => 'encrypted',
            'disposed_at' => 'immutable_datetime',
        ];
    }

    public function checkout(): BelongsTo
    {
        return $this->belongsTo(Checkout::class);
    }

    public function fundingSettlement(): BelongsTo
    {
        return $this->belongsTo(FundingSettlement::class);
    }
}
