<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Enums\FundingRequestStatus;
use LBHurtado\XChange\Enums\FundingRequestType;
use LogicException;

class FundingRequest extends Model
{
    protected $table = 'x_change_funding_requests';

    protected $guarded = [];

    protected $hidden = [
        'idempotency_key_hash',
        'idempotency_fingerprint',
        'external_reference_ciphertext',
        'requester_notes_ciphertext',
        'review_notes_ciphertext',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $request): void {
            $request->reference ??= (string) Str::ulid();
        });

        static::updating(function (): never {
            throw new LogicException('Funding Requests must be changed through guarded actions.');
        });

        static::deleting(function (): never {
            throw new LogicException('Funding Requests cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'funding_type' => FundingRequestType::class,
            'requested_value_minor' => 'integer',
            'approved_value_minor' => 'integer',
            'status' => FundingRequestStatus::class,
            'version' => 'integer',
            'external_reference_ciphertext' => 'encrypted',
            'requester_notes_ciphertext' => 'encrypted',
            'review_notes_ciphertext' => 'encrypted',
            'occurred_on' => 'immutable_date',
            'submitted_at' => 'immutable_datetime',
            'reviewed_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'rejected_at' => 'immutable_datetime',
            'withdrawn_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(FundingRequestEvent::class)->orderBy('sequence');
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function notices(): HasMany
    {
        return $this->hasMany(FundingRequestNotice::class);
    }

    public function transferMatch(): HasOne
    {
        return $this->hasOne(FundingRequestTransferMatch::class);
    }
}
