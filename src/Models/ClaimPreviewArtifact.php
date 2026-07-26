<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ClaimPreviewArtifact extends Model
{
    protected $table = 'x_change_claim_preview_artifacts';

    protected $fillable = [
        'reference',
        'artifact_fingerprint',
        'scenario_key',
        'scenario_version',
        'profile',
        'status',
        'artifact_disk',
        'artifact_path',
        'metadata',
        'generated_at',
        'expires_at',
    ];

    protected $hidden = [
        'artifact_fingerprint',
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
            'scenario_version' => 'integer',
            'metadata' => 'array',
            'generated_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }
}
