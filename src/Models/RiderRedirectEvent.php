<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;

final class RiderRedirectEvent extends Model
{
    protected $table = 'x_change_rider_redirect_events';

    public $timestamps = false;

    protected $fillable = ['reference', 'redirect_id', 'event_type', 'event_key', 'attempt_reference', 'payload', 'occurred_at', 'delivered_at', 'delivery_attempts', 'last_error_type'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'occurred_at' => 'immutable_datetime', 'delivered_at' => 'immutable_datetime'];
    }
}
