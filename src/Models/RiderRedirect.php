<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;

final class RiderRedirect extends Model
{
    protected $table = 'x_change_rider_redirects';

    protected $fillable = ['reference', 'voucher_id', 'destination', 'destination_host', 'fingerprint', 'policy', 'eligible_claim_id', 'eligible_at'];

    protected $hidden = ['destination', 'policy'];

    protected static function booted(): void
    {
        self::updating(function (self $redirect): void {
            if ($redirect->isDirty(['reference', 'voucher_id', 'destination', 'destination_host', 'fingerprint', 'policy'])) {
                throw new \LogicException('Issued rider redirect destinations and policies are immutable.');
            }
        });
    }

    protected function casts(): array
    {
        return ['destination' => 'encrypted', 'policy' => 'array', 'eligible_at' => 'immutable_datetime'];
    }
}
