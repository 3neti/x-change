<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;

final class SettledPaymentProcess extends Model
{
    protected $table = 'x_change_settled_payment_processes';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'sms_enabled_snapshot' => 'boolean',
            'processed_at' => 'immutable_datetime',
        ];
    }
}
