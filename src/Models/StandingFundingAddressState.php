<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LBHurtado\XChange\Casts\UtcImmutableDateTime;
use LBHurtado\XChange\Enums\StandingFundingAddressSyncStatus;

final class StandingFundingAddressState extends Model
{
    protected $table = 'x_change_standing_funding_address_states';

    protected $guarded = [];

    protected $attributes = ['status' => 'idle', 'generation' => 1, 'consecutive_failures' => 0];

    protected function casts(): array
    {
        return [
            'status' => StandingFundingAddressSyncStatus::class,
            'generation' => 'integer',
            'consecutive_failures' => 'integer',
            'lease_expires_at' => UtcImmutableDateTime::class,
            'next_eligible_at' => UtcImmutableDateTime::class,
            'quarantined_at' => UtcImmutableDateTime::class,
            'ambiguous_at' => UtcImmutableDateTime::class,
        ];
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(StandingFundingAddress::class, 'standing_funding_address_id');
    }
}
