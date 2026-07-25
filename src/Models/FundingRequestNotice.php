<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class FundingRequestNotice extends Model
{
    protected $table = 'x_change_funding_request_notices';

    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (self $notice): void {
            $notice->reference ??= (string) Str::ulid();
        });
    }

    protected function casts(): array
    {
        return [
            'action' => 'array',
            'read_at' => 'immutable_datetime',
        ];
    }

    public function fundingRequest(): BelongsTo
    {
        return $this->belongsTo(FundingRequest::class);
    }
}
