<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LBHurtado\XChange\Casts\UtcImmutableDateTime;
use LBHurtado\XChange\Enums\StandingFundingFailureClassification;
use LBHurtado\XChange\Enums\StandingFundingSyncRunStatus;

final class StandingFundingSyncRun extends Model
{
    protected $table = 'x_change_standing_funding_sync_runs';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'generation' => 'integer',
            'status' => StandingFundingSyncRunStatus::class,
            'failure_classification' => StandingFundingFailureClassification::class,
            'summary' => 'array',
            'started_at' => UtcImmutableDateTime::class,
            'finished_at' => UtcImmutableDateTime::class,
        ];
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(StandingFundingAddress::class, 'standing_funding_address_id');
    }
}
