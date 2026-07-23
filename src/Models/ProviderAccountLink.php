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
        'routing_profile_ciphertext',
        'routing_fingerprint',
        'display_reference',
        'ready_at',
        'last_synced_at',
        'verified_at',
        'activated_at',
        'disabled_at',
    ];

    protected $hidden = [
        'routing_profile_ciphertext',
        'routing_fingerprint',
    ];

    protected function casts(): array
    {
        return [
            'capabilities' => 'array',
            'metadata' => 'array',
            'routing_profile_ciphertext' => 'encrypted:array',
            'ready_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'verified_at' => 'immutable_datetime',
            'activated_at' => 'immutable_datetime',
            'disabled_at' => 'immutable_datetime',
        ];
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function isReady(): bool
    {
        return $this->status === 'ready'
            && $this->ready_at !== null
            && $this->disabled_at === null;
    }
}
