<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Number;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(\App\Services\OpenAI\Client::class, fn() => new \App\Services\OpenAI\Client());
    }

    public function boot(): void
    {
        Number::useLocale(config('app.locale'));
    }
}
