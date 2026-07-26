<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LBHurtado\Voucher\Models\Voucher;
use LogicException;

final class SystemAccountFundingPayCodeIssuance extends Model
{
    protected $table = 'x_change_system_account_funding_pay_code_issuances';

    protected $fillable = [
        'reference',
        'idempotency_reference_hash',
        'request_fingerprint',
        'source',
        'issuer_type',
        'issuer_id',
        'recipient_type',
        'recipient_id',
        'bearer',
        'connection_reference',
        'provider_code',
        'amount_minor',
        'currency',
        'evidence_reference',
        'authorization_reference',
        'voucher_id',
        'reservation_operation_reference',
        'status',
        'expires_at',
        'issued_at',
        'metadata',
    ];

    protected $hidden = [
        'idempotency_reference_hash',
        'request_fingerprint',
    ];

    protected static function booted(): void
    {
        self::creating(function (self $issuance): void {
            $issuance->reference ??= (string) Str::ulid();
        });

        self::deleting(function (): never {
            throw new LogicException(
                'System Account Funding Pay Code issuances cannot be deleted.',
            );
        });
    }

    protected function casts(): array
    {
        return [
            'bearer' => 'boolean',
            'amount_minor' => 'integer',
            'voucher_id' => 'integer',
            'expires_at' => 'immutable_datetime',
            'issued_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }
}
