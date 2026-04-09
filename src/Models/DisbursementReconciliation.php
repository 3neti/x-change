<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;

class DisbursementReconciliation extends Model
{
    protected $table = 'disbursement_reconciliations';

    protected $guarded = [];

    protected $casts = [
        'raw_request' => 'array',
        'raw_response' => 'array',
        'meta' => 'array',
        'needs_review' => 'boolean',
        'attempted_at' => 'datetime',
        'completed_at' => 'datetime',
        'last_checked_at' => 'datetime',
        'next_retry_at' => 'datetime',
    ];
}
