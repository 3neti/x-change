<?php

namespace LBHurtado\MoneyIssuer\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \LBHurtado\MoneyIssuer\Services\MoneyIssuerManager
 *
 * Facade for accessing EMI (Electronic Money Issuer) drivers.
 *
 * Example usage:
 *
 * // Use the default configured EMI driver
 * $emi = MoneyIssuer::driver();
 * $balance = $emi->checkBalance('account-123');
 *
 * // Use a specific driver (e.g., 'netbank', 'icash')
 * $netbank = MoneyIssuer::driver('netbank');
 * $transfer = $netbank->transfer('acct-a', 'acct-b', 1000.00, 'PHP');
 *
 * // Optionally, you may register a facade alias in config/app.php:
 * 'aliases' => [
 *     'MoneyIssuer' => \LBHurtado\MoneyIssuer\Facades\MoneyIssuer::class,
 * ],
 *
 * Or dynamically via class_alias() in your service provider's boot():
 * if (!class_exists('MoneyIssuer')) {
 *     class_alias(\LBHurtado\MoneyIssuer\Facades\MoneyIssuer::class, 'MoneyIssuer');
 * }
 */
class MoneyIssuer extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'money-issuer';
    }
}
