<?php

namespace LBHurtado\OmniChannel;

use LBHurtado\OmniChannel\Notifications\OmniChannelSmsChannel;
use LBHurtado\OmniChannel\Services\OmniChannelService;
use LBHurtado\OmniChannel\Services\SMSRouterService;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\File;

class OmniChannelServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/omnichannel.php',
            'omnichannel'
        );

        $this->app->singleton(SMSRouterService::class, fn() => new SMSRouterService());

        // Bind your core OmniChannel service
        $this->app->singleton(OmniChannelService::class, fn() => new OmniChannelService(
            config('omnichannel.url'),
            config('omnichannel.access_key'),
            config('omnichannel.service'),
        ));

        $handlers = config('omnichannel.handlers.auto_replies');
        if (! is_array($handlers) || empty($handlers)) {
            throw new \RuntimeException('Invalid omnichannel.auto_replies config');
        }
    }

    public function boot(ChannelManager $channels): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->publishes([
            __DIR__ . '/../config/omnichannel.php' => config_path('omnichannel.php'),
        ], 'omnichannel-config');

        $this->publishes([
            __DIR__ . '/../routes/sms.php' => base_path('routes/sms.php'),
        ], 'omnichannel-routes');

        // Register the "omnichannel" notification channel
        $channels->extend('omnichannel', fn($app) => $app->make(OmniChannelSmsChannel::class));

        $this->registerRoutes();
    }

    protected function registerRoutes(): void
    {
        // don't load routes if they're cached
        if ($this->app->routesAreCached()) {
            return;
        }

        // core API routes
        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');

//        // package's SMS routes
//        $this->loadRoutesFrom(__DIR__ . '/../routes/sms.php');
//
//        // optional app-level override
//        $appSms = base_path('routes/sms.php');
//        if (File::exists($appSms)) {
//            require $appSms;
//        }

        $app = base_path('routes/sms.php');

        if (File::exists($app)) {
            // load the *app’s* sms.php → this becomes the only sms route
            require $app;
        } else {
            $this->loadRoutesFrom(__DIR__ . '/../routes/sms.php');
        }
    }
}
