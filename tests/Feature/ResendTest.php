<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\EngageSpark\Notifications\Adhoc;
use App\Notifications\TestNotification;

uses(RefreshDatabase::class);

it('resend works', function () {
    $resend = Resend::client('re_BMt2994j_8tvPdv7JrhepUsAsreAQJZEn');

    $resend->emails->send([
        'from' => 'Acme <admin@disburse.cash>',
        'to' => ['lester@hurtado.ph'],
        'subject' => 'hello world',
        'html' => '<p>it works!</p>'
    ]);
})->skip();
