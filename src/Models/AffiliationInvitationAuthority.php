<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XProvisioning\Models\ProvisioningOffer;

final class AffiliationInvitationAuthority extends Model
{
    protected $table = 'x_change_affiliation_invitation_authorities';

    protected $fillable = [
        'voucher_id',
        'provisioning_offer_id',
        'encrypted_claim_token',
        'snapshot_hash',
    ];

    protected $hidden = ['encrypted_claim_token'];

    protected function casts(): array
    {
        return [
            'encrypted_claim_token' => 'encrypted',
        ];
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function provisioningOffer(): BelongsTo
    {
        return $this->belongsTo(ProvisioningOffer::class);
    }
}
