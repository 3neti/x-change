<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LBHurtado\XChange\Enums\PublicAutoRegistrationStatus;

final class PublicAutoRegistration extends Model
{
    protected $table = 'x_change_public_auto_registrations';
    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (self $registration): void {
            $registration->reference ??= (string) Str::ulid();
        });
    }

    protected function casts(): array
    {
        return [
            'status' => PublicAutoRegistrationStatus::class,
            'amount_minor' => 'integer',
            'metadata' => 'array',
            'paid_at' => 'immutable_datetime',
            'activated_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }

    public function fundingIntent(): BelongsTo
    {
        return $this->belongsTo(FundingIntent::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(\LBHurtado\Contact\Models\Contact::class);
    }
}
