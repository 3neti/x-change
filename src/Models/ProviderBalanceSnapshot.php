<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\XChange\Casts\UtcImmutableDateTime;

class ProviderBalanceSnapshot extends Model
{
    protected $table = 'x_change_provider_balance_snapshots';

    protected $fillable = [
        'provider_code',
        'balance_key',
        'scope_key',
        'balance_minor',
        'available_balance_minor',
        'currency',
        'account_reference_masked',
        'provider_as_of',
        'fetched_at',
        'refresh_status',
        'failure_reason',
        'last_refresh_failed_at',
    ];

    protected $attributes = [
        'scope_key' => 'global',
        'currency' => 'PHP',
        'refresh_status' => 'unavailable',
    ];

    protected function casts(): array
    {
        return [
            'balance_minor' => 'integer',
            'available_balance_minor' => 'integer',
            'provider_as_of' => UtcImmutableDateTime::class,
            'fetched_at' => UtcImmutableDateTime::class,
            'last_refresh_failed_at' => UtcImmutableDateTime::class,
        ];
    }
}
