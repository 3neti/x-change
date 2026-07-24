<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class StandingFundingQrArtifact extends Model
{
    protected $table = 'x_change_standing_funding_qr_artifacts';

    protected $fillable = [
        'reference',
        'standing_funding_address_id',
        'status',
        'version',
        'artifact_fingerprint',
        'merchant_profile_fingerprint',
        'mime_type',
        'qr_mode',
        'transaction_type',
        'embedded_amount',
        'provider_generated',
        'payload_ciphertext',
        'display_snapshot_ciphertext',
        'generated_at',
        'invalidated_at',
    ];

    protected $hidden = [
        'artifact_fingerprint',
        'merchant_profile_fingerprint',
        'payload_ciphertext',
        'display_snapshot_ciphertext',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $artifact): void {
            $artifact->reference ??= (string) Str::ulid();
        });
    }

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'embedded_amount' => 'boolean',
            'provider_generated' => 'boolean',
            'payload_ciphertext' => 'encrypted',
            'display_snapshot_ciphertext' => 'encrypted:array',
            'generated_at' => 'immutable_datetime',
            'invalidated_at' => 'immutable_datetime',
        ];
    }

    public function standingFundingAddress(): BelongsTo
    {
        return $this->belongsTo(StandingFundingAddress::class);
    }
}
