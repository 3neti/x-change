<?php

namespace LBHurtado\Voucher\Models;

use FrittenKeeZ\Vouchers\Models\Voucher as BaseVoucher;
use LBHurtado\Voucher\Scopes\RedeemedScope;

class Voucher extends BaseVoucher
{
    protected function casts(): array
    {
        // Include parent's casts and add/override
        return array_merge(parent::casts(), [
            'processed_on' => 'datetime:Y-m-d H:i:s',
        ]);
    }

    public function setProcessedAttribute(bool $value): self
    {
        $this->setAttribute('processed_on', $value ? now() : null);

        return $this;
    }

    public function getProcessedAttribute(): bool
    {
        return $this->getAttribute('processed_on')
            && $this->getAttribute('processed_on') <= now();
    }
}
