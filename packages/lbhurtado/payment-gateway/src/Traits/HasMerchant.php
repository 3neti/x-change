<?php

namespace LBHurtado\PaymentGateway\Traits;

use LBHurtado\PaymentGateway\Models\Merchant;

trait HasMerchant
{
    /**
     * Define the belongsToMany relationship to the Merchant model.
     */
    public function merchant()
    {
        return $this->belongsToMany(Merchant::class, 'merchant_user', 'user_id', 'merchant_id')->withTimestamps();
    }

    /**
     * Associate a merchant with the user.
     */
    public function setMerchant(Merchant $merchant): static
    {
        // Enforce single merchant association
        $this->merchant()->sync($merchant);

        return $this;
    }

    /**
     * Set the merchant attribute via the accessor.
     */
    public function setMerchantAttribute(Merchant|string $merchant): void
    {
        if ($merchant instanceof Merchant) {
            $this->setMerchant($merchant);
        } elseif (is_numeric($merchant)) {
            $this->setMerchant(Merchant::find($merchant));
        }
    }

    /**
     * Get the currently associated merchant via the accessor.
     */
    public function getMerchantAttribute()
    {
        return $this->merchant()->first();
    }
}
