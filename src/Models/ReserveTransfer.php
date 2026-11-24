<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

final class ReserveTransfer extends Model
{
    protected $table = 'x_change_reserve_transfers';

    protected $guarded = [];

    protected $hidden = ['request_ciphertext', 'bank_evidence_ciphertext', 'treasury_binding_ciphertext', 'issuance_snapshot_ciphertext', 'issuance_snapshot_fingerprint', 'idempotency_hash', 'request_fingerprint', 'submission_fingerprint'];

    protected function casts(): array
    {
        return [
            'request_ciphertext' => 'encrypted:array',
            'bank_evidence_ciphertext' => 'encrypted:array',
            'treasury_binding_ciphertext' => 'encrypted:array',
            'issuance_snapshot_ciphertext' => 'encrypted:array',
            'amount_minor' => 'integer',
            'fee_minor' => 'integer',
            'voucher_id' => 'integer',
            'funding_order_id' => 'integer',
            'bank_account_link_id' => 'integer',
            'held_at' => 'immutable_datetime',
            'submitted_at' => 'immutable_datetime',
            'provider_settled_at' => 'immutable_datetime',
            'treasury_posted_at' => 'immutable_datetime',
            'issued_at' => 'immutable_datetime',
        ];
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }
}
