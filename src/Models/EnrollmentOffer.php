<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

final class EnrollmentOffer extends Model
{
    protected $table = 'x_change_enrollment_offers';

    protected $fillable = ['reference', 'owner_type', 'owner_id', 'title', 'consent_statement', 'consent_version', 'active'];

    protected static function booted(): void
    {
        self::creating(function (self $offer): void {
            $offer->reference ??= (string) Str::ulid();
        });
    }

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function consents(): HasMany
    {
        return $this->hasMany(EnrollmentConsent::class, 'enrollment_offer_id');
    }
}
