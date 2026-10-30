<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;

final class V2EvidenceOutbox extends Model
{
    protected $table = 'x_change_v2_evidence_outbox';

    protected $fillable = ['event_key', 'voucher_id', 'event_type', 'evidence_reference', 'payload', 'occurred_at'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'occurred_at' => 'immutable_datetime', 'delivered_at' => 'immutable_datetime'];
    }
}
