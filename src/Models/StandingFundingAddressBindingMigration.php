<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;
use LBHurtado\XChange\Enums\StandingFundingAddressBindingMigrationStatus;

final class StandingFundingAddressBindingMigration extends Model
{
    protected $table = 'x_change_standing_funding_address_binding_migrations';

    protected $attributes = ['status' => 'awaiting_approval'];

    protected $fillable = [
        'reference', 'standing_funding_address_id', 'status',
        'from_account_reference_ciphertext', 'from_account_reference_hash',
        'to_account_reference_ciphertext', 'to_account_reference_hash', 'proposed_binding_key',
        'proposed_destination_snapshot_ciphertext', 'proposed_destination_fingerprint',
        'evidence_snapshot', 'evidence_hash', 'idempotency_key_hash',
        'maker_type', 'maker_id', 'requested_at', 'checker_type', 'checker_id',
        'approval_reference', 'approved_at', 'activated_by_type', 'activated_by_id',
        'activated_binding_revision_id', 'activated_at',
    ];

    protected $hidden = [
        'from_account_reference_ciphertext', 'from_account_reference_hash',
        'to_account_reference_ciphertext', 'to_account_reference_hash',
        'proposed_binding_key', 'proposed_destination_snapshot_ciphertext',
        'proposed_destination_fingerprint', 'evidence_snapshot', 'idempotency_key_hash',
    ];

    protected static function booted(): void
    {
        self::creating(function (self $migration): void {
            $migration->reference ??= (string) Str::ulid();
        });

        self::updating(function (self $migration): void {
            $allowed = [
                'status', 'checker_type', 'checker_id', 'approval_reference', 'approved_at',
                'activated_by_type', 'activated_by_id', 'activated_binding_revision_id',
                'activated_at', 'updated_at',
            ];

            if (array_diff(array_keys($migration->getDirty()), $allowed) !== []) {
                throw new \LogicException('Standing Funding Address migration evidence is immutable.');
            }
        });

        self::deleting(function (): never {
            throw new \LogicException('Standing Funding Address binding migrations cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'status' => StandingFundingAddressBindingMigrationStatus::class,
            'from_account_reference_ciphertext' => 'encrypted',
            'to_account_reference_ciphertext' => 'encrypted',
            'proposed_destination_snapshot_ciphertext' => 'encrypted:array',
            'evidence_snapshot' => 'array',
            'requested_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
            'activated_at' => 'immutable_datetime',
        ];
    }

    public function standingFundingAddress(): BelongsTo
    {
        return $this->belongsTo(StandingFundingAddress::class);
    }

    public function maker(): MorphTo
    {
        return $this->morphTo();
    }

    public function checker(): MorphTo
    {
        return $this->morphTo();
    }

    public function activatedBy(): MorphTo
    {
        return $this->morphTo();
    }

    public function activatedBindingRevision(): BelongsTo
    {
        return $this->belongsTo(
            StandingFundingAddressBindingRevision::class,
            'activated_binding_revision_id',
        );
    }
}
