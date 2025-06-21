<?php

use LBHurtado\OmniChannel\Notifications\AdhocNotification;
use LBHurtado\OmniChannel\Services\OmniChannelService;
use LBHurtado\OmniChannel\Tests\Models\User;

it('can send sms', function () {
//    dd(config('omnichannel'));
    app(OmniChannelService::class)->send('09173011987', 'Test SMS');
})->skip();

it('can send notification', function () {
    $user = User::factory()->create();
    $user->notify(new AdhocNotification('LESLIE CHIONG'));
})->skip();
