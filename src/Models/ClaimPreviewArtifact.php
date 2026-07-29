<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class ClaimPreviewArtifact extends Model
{
    protected $table = 'x_change_claim_preview_artifacts';

    protected $fillable = [
        'reference',
        'owner_type',
        'owner_id',
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

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    public function isOwnedBy(Authenticatable $owner): bool
    {
        return $owner instanceof Model
            && $this->owner_type === $owner->getMorphClass()
            && (string) $this->owner_id === (string) $owner->getKey();
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
