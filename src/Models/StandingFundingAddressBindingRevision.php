<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;
use LBHurtado\XChange\Casts\UtcImmutableDateTime;

final class StandingFundingAddressBindingRevision extends Model
{
    protected $table = 'x_change_standing_funding_address_binding_revisions';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = [
        'reference', 'standing_funding_address_id', 'binding_version',
        'previous_binding_revision_id', 'account_reference_ciphertext',
        'account_reference_hash', 'binding_key',
        'destination_snapshot_ciphertext', 'destination_fingerprint', 'reason',
        'evidence_snapshot', 'evidence_hash',
        'approval_reference', 'activated_by_type', 'activated_by_id', 'effective_at',
    ];

    protected $hidden = [
        'account_reference_ciphertext', 'account_reference_hash', 'binding_key',
        'destination_snapshot_ciphertext', 'destination_fingerprint', 'evidence_snapshot',
    ];

    protected static function booted(): void
    {
        self::creating(function (self $revision): void {
            $revision->reference ??= (string) Str::ulid();
        });

        self::updating(function (): never {
            throw new \LogicException('Standing Funding Address binding revisions are immutable.');
        });

        self::deleting(function (): never {
            throw new \LogicException('Standing Funding Address binding revisions cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'binding_version' => 'integer',
            'account_reference_ciphertext' => 'encrypted',
            'destination_snapshot_ciphertext' => 'encrypted:array',
            'evidence_snapshot' => 'array',
            'effective_at' => UtcImmutableDateTime::class,
        ];
    }

    public function standingFundingAddress(): BelongsTo
    {
        return $this->belongsTo(StandingFundingAddress::class);
    }

    public function previousBindingRevision(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_binding_revision_id');
    }

    public function activatedBy(): MorphTo
    {
        return $this->morphTo();
    }

    public function effectiveTimeCorrection(): HasOne
    {
        return $this->hasOne(
            StandingFundingAddressBindingEffectiveTimeCorrection::class,
            'standing_funding_address_binding_revision_id',
        );
    }
}
