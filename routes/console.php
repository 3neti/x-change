<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

Schedule::command('xchange:reconcile:pending --limit=50')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();
