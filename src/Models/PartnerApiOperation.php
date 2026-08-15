<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerApiOperation extends Model
{
    protected $table = 'x_change_partner_api_operations';

    protected $fillable = [
        'partner_api_client_id',
        'operation',
        'idempotency_key',
        'correlation_id',
        'subject_reference',
        'principal_minor',
        'currency',
        'occurred_at',
    ];

    protected $attributes = [
        'principal_minor' => 0,
    ];

    protected function casts(): array
    {
        return [
            'principal_minor' => 'integer',
            'occurred_at' => 'immutable_datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(PartnerApiClient::class, 'partner_api_client_id');
    }
}
