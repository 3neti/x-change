<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;
use LBHurtado\XChange\Enums\RiderLibraryEntryKind;

class RiderLibraryEntry extends Model
{
    protected $table = 'x_change_rider_library_entries';

    protected $attributes = [
        'use_count' => 0,
    ];

    protected $fillable = [
        'owner_type',
        'owner_id',
        'kind',
        'format',
        'content_ciphertext',
        'label_ciphertext',
        'content_fingerprint',
        'saved_at',
        'pinned_at',
        'use_count',
        'first_used_at',
        'last_used_at',
    ];

    protected $hidden = [
        'content_ciphertext',
        'label_ciphertext',
        'content_fingerprint',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $entry): void {
            $entry->reference ??= (string) Str::ulid();
        });
    }

    protected function casts(): array
    {
        return [
            'kind' => RiderLibraryEntryKind::class,
            'content_ciphertext' => 'encrypted:array',
            'label_ciphertext' => 'encrypted',
            'saved_at' => 'immutable_datetime',
            'pinned_at' => 'immutable_datetime',
            'use_count' => 'integer',
            'first_used_at' => 'immutable_datetime',
            'last_used_at' => 'immutable_datetime',
        ];
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }
}
