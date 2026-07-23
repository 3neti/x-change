<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class FundingDestinationPreference extends Model
{
    protected $table = 'x_change_funding_destination_preferences';

    protected $fillable = [
        'owner_type',
        'owner_id',
        'provider_code',
        'mode',
        'provider_account_link_id',
        'version',
        'changed_by_type',
        'changed_by_id',
    ];

    protected $attributes = [
        'mode' => 'shared',
        'version' => 1,
    ];

    protected function casts(): array
    {
        return [
            'provider_account_link_id' => 'integer',
            'version' => 'integer',
        ];
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function providerAccountLink(): BelongsTo
    {
        return $this->belongsTo(ProviderAccountLink::class);
    }
}
