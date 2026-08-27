<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class ExternalJobFailure extends Model
{
    protected $table = 'x_change_external_job_failures';

    protected $fillable = [
        'job_type',
        'subject_type',
        'subject_id',
        'provider_code',
        'trigger',
        'failure_type',
        'metadata',
        'failed_at',
    ];

    protected static function booted(): void
    {
        self::creating(function (self $failure): void {
            $failure->reference ??= (string) Str::ulid();
        });

        self::updating(function (): never {
            throw new \LogicException('External job failures are append-only.');
        });

        self::deleting(function (): never {
            throw new \LogicException('External job failures are append-only.');
        });
    }

    protected function casts(): array
    {
        return [
            'failed_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }
}
