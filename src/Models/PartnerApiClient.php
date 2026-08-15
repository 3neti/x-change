<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LBHurtado\XChange\Enums\PartnerApiClientStatus;

class PartnerApiClient extends Model
{
    protected $table = 'x_change_partner_api_clients';

    protected $fillable = [
        'reference',
        'oauth_client_id',
        'name',
        'issuer_type',
        'issuer_id',
        'environment',
        'status',
        'scopes',
        'mandate',
        'activated_at',
        'suspended_at',
        'revoked_at',
    ];

    protected $attributes = [
        'environment' => 'sandbox',
        'status' => 'draft',
        'scopes' => '[]',
        'mandate' => '{}',
    ];

    protected function casts(): array
    {
        return [
            'status' => PartnerApiClientStatus::class,
            'scopes' => 'array',
            'mandate' => 'array',
            'activated_at' => 'immutable_datetime',
            'suspended_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    public function issuer(): MorphTo
    {
        return $this->morphTo('issuer');
    }

    public function isActive(): bool
    {
        return $this->status === PartnerApiClientStatus::Active
            && $this->activated_at !== null
            && $this->suspended_at === null
            && $this->revoked_at === null;
    }
}
