<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;
use App\Events\SessionMobileStored;
use App\Actions\VerifyMobile;

class EventServiceProvider extends ServiceProvider
{
    public function register() { /* … */ }

    public function boot()
    {
        Event::listen(
            events: SessionMobileStored::class,
            listener: VerifyMobile::class,
        );
    }
}
