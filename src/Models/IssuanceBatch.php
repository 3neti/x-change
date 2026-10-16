<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LBHurtado\XChange\Enums\IssuanceBatchStatus;

final class IssuanceBatch extends Model
{
    protected $table = 'x_change_issuance_batches';

    protected $guarded = [];

    protected static function booted(): void
    {
        self::creating(function (self $batch): void {
            $batch->reference ??= (string) Str::ulid();
        });
    }

    protected function casts(): array
    {
        return [
            'status' => IssuanceBatchStatus::class,
            'total_minor' => 'integer',
            'item_count' => 'integer',
            'valid_item_count' => 'integer',
            'metadata' => 'array',
            'access_token_ciphertext' => 'encrypted',
            'uploader_mobile_ciphertext' => 'encrypted',
            'expires_at' => 'immutable_datetime',
            'funded_at' => 'immutable_datetime',
            'issued_at' => 'immutable_datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(IssuanceBatchItem::class, 'batch_id')->orderBy('ordinal');
    }

    public function fundingOrder(): BelongsTo
    {
        return $this->belongsTo(PayCodeIssuanceFundingOrder::class, 'funding_order_id');
    }
}
