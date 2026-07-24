<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class LifecycleMoneyRun extends Model
{
    protected $table = 'x_change_lifecycle_money_runs';

    protected $fillable = [
        'reference',
        'scenario_key',
        'run_reference_hash',
        'run_fingerprint',
        'issuer_type',
        'issuer_id',
        'provider_code',
        'amount_minor',
        'currency',
        'status',
        'voucher_id',
        'result_summary',
        'failure_reason',
        'started_at',
        'completed_at',
    ];

    protected $hidden = [
        'run_reference_hash',
        'run_fingerprint',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $run): void {
            $run->reference ??= (string) Str::ulid();
        });

        static::deleting(function (): never {
            throw new \LogicException('Lifecycle money runs cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'voucher_id' => 'integer',
            'result_summary' => 'array',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    public function issuer(): MorphTo
    {
        return $this->morphTo();
    }
}
