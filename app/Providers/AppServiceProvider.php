<?php

namespace App\Providers;

use LBHurtado\Wallet\Exceptions\SystemUserNotFoundException;
use LBHurtado\Wallet\Services\SystemUserResolverService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
//        if (app()->environment('testing')) {
//            return;
//        }
//
//        try {
//            app(SystemUserResolverService::class)->resolve();
//        } catch (SystemUserNotFoundException $e) {
//            abort(500, 'System user not configured properly.');
//        }
    }
}
