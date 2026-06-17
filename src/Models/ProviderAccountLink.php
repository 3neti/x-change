<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ProviderAccountLink extends Model
{
    protected $table = 'xchange_provider_account_links';

    protected $fillable = [
        'owner_type',
        'owner_id',
        'provider',
        'topology',
        'purpose',
        'mode',
        'emi_provider_account_id',
        'emi_wallet_id',
        'emi_bank_account_id',
        'provider_account_id',
        'provider_wallet_id',
        'provider_bank_account_id',
        'external_uid',
        'status',
        'verification_status',
        'identity_level',
        'capabilities',
        'metadata',
        'ready_at',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'capabilities' => 'array',
            'metadata' => 'array',
            'ready_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function isReady(): bool
    {
        return $this->status === 'ready' && $this->ready_at !== null;
    }
}
