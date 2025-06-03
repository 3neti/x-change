<?php

namespace LBHurtado\PaymentGateway\Contracts;

use LBHurtado\PaymentGateway\Models\Merchant;

interface HasMerchantInterface
{
    /**
     * Get the associated merchant for the model.
     */
    public function getMerchantAttribute();

    /**
     * Set the merchant for the model.
     */
    public function setMerchant(Merchant $merchant): static;
}
