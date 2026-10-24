<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class EnrollmentConsent extends Model
{
    protected $table = 'x_change_enrollment_consents';

    protected $fillable = [
        'reference', 'enrollment_offer_id', 'name_ciphertext', 'mobile_ciphertext',
        'mobile_hash', 'consent_version', 'statement_hash', 'consented_at',
    ];

    protected $hidden = ['name_ciphertext', 'mobile_ciphertext', 'mobile_hash'];

    protected static function booted(): void
    {
        self::creating(function (self $consent): void {
            $consent->reference ??= (string) Str::ulid();
        });
    }

    protected function casts(): array
    {
        return [
            'name_ciphertext' => 'encrypted',
            'mobile_ciphertext' => 'encrypted',
            'consented_at' => 'immutable_datetime',
        ];
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(EnrollmentOffer::class, 'enrollment_offer_id');
    }
}
