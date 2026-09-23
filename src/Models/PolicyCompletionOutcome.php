<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;
use LBHurtado\XChange\Enums\PolicyCompletionOutcomeStatus;

final class PolicyCompletionOutcome extends Model
{
    protected $table = 'x_change_policy_completion_outcomes';

    protected $guarded = [];

    protected $hidden = ['private_result', 'outcome_hash'];

    protected static function booted(): void
    {
        self::creating(function (self $outcome): void {
            $outcome->reference ??= (string) Str::ulid();
        });
        self::updating(fn (): never => throw new \LogicException('Policy completion outcomes are immutable.'));
        self::deleting(fn (): never => throw new \LogicException('Policy completion outcomes cannot be deleted.'));
    }

    protected function casts(): array
    {
        return [
            'status' => PolicyCompletionOutcomeStatus::class,
            'safe_result' => 'array',
            'private_result' => 'encrypted:array',
            'recorded_at' => 'immutable_datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(PolicyCompletionRequest::class, 'policy_completion_request_id');
    }

    public function recordedBy(): MorphTo
    {
        return $this->morphTo();
    }
}
