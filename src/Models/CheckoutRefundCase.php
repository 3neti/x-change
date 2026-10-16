<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Models\Voucher;

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
            'hold_until' => 'immutable_datetime',
            'eligible_at' => 'immutable_datetime',
            'metadata' => 'array',
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

    public function recipientContact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'recipient_contact_id');
    }

    public function refundPayCode(): BelongsTo
    {
        return $this->belongsTo(Voucher::class, 'refund_pay_code_id');
    }
}
