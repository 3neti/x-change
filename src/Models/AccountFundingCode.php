<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LBHurtado\XChange\Enums\AccountFundingCodeStatus;
use LogicException;

class AccountFundingCode extends Model
{
    protected $table = 'x_change_account_funding_codes';

    protected $guarded = [];

    protected $hidden = ['code_hash', 'code_ciphertext'];

    protected static function booted(): void
    {
        static::creating(function (self $code): void {
            $code->reference ??= (string) Str::ulid();
        });

        static::updating(function (): never {
            throw new LogicException('Account Funding Codes must be changed through guarded actions.');
        });

        static::deleting(function (): never {
            throw new LogicException('Account Funding Codes cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'code_ciphertext' => 'encrypted',
            'amount_minor' => 'integer',
            'status' => AccountFundingCodeStatus::class,
            'version' => 'integer',
            'issued_at' => 'immutable_datetime',
            'claimed_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    public function fundingRequest(): BelongsTo
    {
        return $this->belongsTo(FundingRequest::class);
    }
}
