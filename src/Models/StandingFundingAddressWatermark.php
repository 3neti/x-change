<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LBHurtado\XChange\Casts\UtcImmutableDateTime;

final class StandingFundingAddressWatermark extends Model
{
    protected $table = 'x_change_standing_funding_address_watermarks';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'baselined_at' => UtcImmutableDateTime::class,
        ];
    }

    public function standingFundingAddress(): BelongsTo
    {
        return $this->belongsTo(StandingFundingAddress::class);
    }
}
