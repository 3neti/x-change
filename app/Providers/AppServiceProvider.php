<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(\App\Services\OpenAI\Client::class, fn() => new \App\Services\OpenAI\Client());
    }

    public function boot(): void { /* … */ }
}
