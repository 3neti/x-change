<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;

final class PartnerPaymentEvent extends Model
{
    protected $table = 'x_change_partner_payment_events';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['available_at' => 'immutable_datetime', 'lease_expires_at' => 'immutable_datetime', 'delivered_at' => 'immutable_datetime', 'attempts' => 'integer'];
    }
}
