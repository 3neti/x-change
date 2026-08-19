<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LBHurtado\XChange\Casts\UtcImmutableDateTime;
use LogicException;

class PartnerApiOperation extends Model
{
    protected $table = 'x_change_partner_api_operations';

    protected $fillable = [
        'partner_api_client_id',
        'reference',
        'operation',
        'idempotency_key',
        'correlation_id',
        'subject_reference',
        'principal_minor',
        'currency',
        'request_hash',
        'balance_after_minor',
        'authority_reference_hash',
        'treasury_operation_reference_hash',
        'response_snapshot',
        'occurred_at',
    ];

    protected $attributes = [
        'principal_minor' => 0,
    ];

    protected $hidden = [
        'idempotency_key',
        'request_hash',
        'authority_reference_hash',
        'treasury_operation_reference_hash',
        'response_snapshot',
    ];

    protected static function booted(): void
    {
        self::creating(function (self $operation): void {
            $operation->reference ??= (string) Str::ulid();
        });

        self::updating(function (): never {
            throw new LogicException('Partner API operation evidence is append-only.');
        });

        self::deleting(function (): never {
            throw new LogicException('Partner API operation evidence cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'principal_minor' => 'integer',
            'balance_after_minor' => 'integer',
            'response_snapshot' => 'array',
            'occurred_at' => UtcImmutableDateTime::class,
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(PartnerApiClient::class, 'partner_api_client_id');
    }
}
