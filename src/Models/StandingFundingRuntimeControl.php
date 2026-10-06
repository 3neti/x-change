<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\XChange\Casts\UtcImmutableDateTime;
use LBHurtado\XChange\Enums\StandingFundingRuntimeMode;

final class StandingFundingRuntimeControl extends Model
{
    protected $table = 'x_change_standing_funding_runtime_controls';

    protected $guarded = [];

    protected $attributes = ['mode' => 'disabled', 'generation' => 1, 'batch_limit' => 1, 'backlog_ceiling' => 25, 'consecutive_failures' => 0];

    protected function casts(): array
    {
        return [
            'mode' => StandingFundingRuntimeMode::class,
            'generation' => 'integer',
            'batch_limit' => 'integer',
            'backlog_ceiling' => 'integer',
            'consecutive_failures' => 'integer',
            'circuit_open_until' => UtcImmutableDateTime::class,
            'transitioned_at' => UtcImmutableDateTime::class,
        ];
    }
}
