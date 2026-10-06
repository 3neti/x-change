<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\XChange\Casts\UtcImmutableDateTime;

final class StandingFundingRuntimeOutbox extends Model
{
    protected $table = 'x_change_standing_funding_runtime_outbox';

    protected $guarded = [];

    protected $attributes = ['journal_status' => 'pending', 'broadcast_status' => 'pending', 'journal_attempts' => 0, 'broadcast_attempts' => 0];

    protected function casts(): array
    {
        return [
            'generation' => 'integer',
            'payload' => 'array',
            'journal_attempts' => 'integer',
            'broadcast_attempts' => 'integer',
            'occurred_at' => UtcImmutableDateTime::class,
            'journaled_at' => UtcImmutableDateTime::class,
            'broadcast_at' => UtcImmutableDateTime::class,
        ];
    }
}
