<?php

namespace LBHurtado\MoneyIssuer;

use LBHurtado\MoneyIssuer\Contracts\MoneyIssuerServiceInterface;
use LBHurtado\MoneyIssuer\Services\MoneyIssuerManager;
use LBHurtado\MoneyIssuer\Support\BankRegistry;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Number;

/**
 * Registers the MoneyIssuer service and its dependencies.
 *
 * This service provider:
 * - Registers the EmiManager as a singleton.
 * - Provides a 'money-issuer' service alias for facade usage.
 * - Binds EmiServiceInterface to the default EMI driver.
 * - Sets the default currency to PHP using Laravel's Number facade.
 *
 * Usage Examples:
 *
 * // Get the default EMI service (from emi.default in config)
 * $emi = app(EmiServiceInterface::class);
 * $emi->checkBalance('acc-123');
 *
 * // Use the manager directly (for multi-driver setups)
 * $manager = app('money-issuer');
 * $icash = $manager->driver('icash');
 * $netbank = $manager->driver('netbank');
 * $netbank->deposit('acc-789', 1000);
 *
 * // Use the facade (if registered)
 * use MoneyIssuer;
 * $transfer = MoneyIssuer::driver('icash')->transfer('a', 'b', 500.0);
 */
class MoneyIssuerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register the EmiManager singleton
        $this->app->singleton(MoneyIssuerManager::class, function ($app) {
            return new MoneyIssuerManager($app);
        });

        // Alias it to 'money-issuer' for easy access via app('money-issuer') or the facade
        $this->app->alias(MoneyIssuerManager::class, 'money-issuer');

        // Bind the EmiServiceInterface to the default driver, so you can type-hint it directly
        $this->app->bind(MoneyIssuerServiceInterface::class, function ($app) {
            return $app->make(MoneyIssuerManager::class)->driver();
        });

        $this->app->singleton(BankRegistry::class, fn () => new BankRegistry());
    }

    public function boot(): void
    {
        // Use PHP as the default currency globally
        Number::useCurrency('PHP');
    }
}
