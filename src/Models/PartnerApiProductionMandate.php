<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LBHurtado\XChange\Enums\PartnerApiProductionMandateStatus;

final class PartnerApiProductionMandate extends Model
{
    protected $table = 'x_change_partner_api_production_mandates';

    protected $fillable = [
        'reference', 'name', 'issuer_type', 'issuer_id', 'status', 'scopes', 'mandate',
        'snapshot_hash', 'maker_type', 'maker_id', 'checker_type', 'checker_id',
        'partner_api_client_id', 'submitted_at', 'approved_at', 'activated_at',
    ];

    protected $attributes = [
        'status' => 'awaiting_approval',
        'scopes' => '[]',
        'mandate' => '{}',
    ];

    protected function casts(): array
    {
        return [
            'status' => PartnerApiProductionMandateStatus::class,
            'scopes' => 'array',
            'mandate' => 'array',
            'submitted_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
            'activated_at' => 'immutable_datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    public function issuer(): MorphTo
    {
        return $this->morphTo();
    }

    public function maker(): MorphTo
    {
        return $this->morphTo();
    }

    public function checker(): MorphTo
    {
        return $this->morphTo();
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(PartnerApiClient::class, 'partner_api_client_id');
    }
}
