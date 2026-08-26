<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LBHurtado\Voucher\Models\Voucher;
use LogicException;

class PartnerApiPayCodeReference extends Model
{
    protected $table = 'x_change_partner_api_pay_code_references';

    protected $fillable = [
        'partner_api_client_id',
        'external_reference',
        'voucher_id',
        'terms_hash',
    ];

    protected $hidden = [
        'terms_hash',
    ];

    protected static function booted(): void
    {
        self::updating(function (): never {
            throw new LogicException('Partner API Pay Code references are append-only.');
        });

        self::deleting(function (): never {
            throw new LogicException('Partner API Pay Code references cannot be deleted.');
        });
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(PartnerApiClient::class, 'partner_api_client_id');
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }
}
