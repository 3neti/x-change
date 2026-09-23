<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;
use LBHurtado\XChange\Enums\PolicyCompletionRequestStatus;

final class PolicyCompletionRequest extends Model
{
    protected $table = 'x_change_policy_completion_requests';

    protected $guarded = [];

    protected $hidden = ['private_payload', 'preparation_fingerprint'];

    protected static function booted(): void
    {
        self::creating(function (self $request): void {
            $request->reference ??= (string) Str::ulid();
        });
        self::updating(fn (): never => throw new \LogicException('Policy completion requests may only transition through governed actions.'));
        self::deleting(fn (): never => throw new \LogicException('Policy completion requests cannot be deleted.'));
    }

    protected function casts(): array
    {
        return [
            'status' => PolicyCompletionRequestStatus::class,
            'safe_context' => 'array',
            'private_payload' => 'encrypted:array',
            'requested_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
        ];
    }

    public function projection(): BelongsTo
    {
        return $this->belongsTo(CompletionClaimEvidenceProjection::class, 'completion_claim_evidence_projection_id');
    }

    public function requester(): MorphTo
    {
        return $this->morphTo();
    }

    public function approver(): MorphTo
    {
        return $this->morphTo();
    }

    public function outcome(): HasOne
    {
        return $this->hasOne(PolicyCompletionOutcome::class);
    }
}
