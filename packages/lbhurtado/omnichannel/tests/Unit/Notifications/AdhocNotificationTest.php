<?php

use Illuminate\Notifications\Notification;
use LBHurtado\OmniChannel\Services\OmniChannelService;
use LBHurtado\OmniChannel\Notifications\AdhocNotification;
use LBHurtado\OmniChannel\Notifications\OmniChannelSmsMessage;
use LBHurtado\OmniChannel\Notifications\OmniChannelSmsChannel;
use Propaganistas\LaravelPhone\PhoneNumber;
use Illuminate\Notifications\Notifiable;

beforeEach(function () {
    // Spy on the service so we can assert send() calls
    $this->sms = Mockery::spy(OmniChannelService::class);
    $this->app->instance(OmniChannelService::class, $this->sms);

    // Make sure the channel is registered
    $this->channel = $this->app->make(OmniChannelSmsChannel::class);
});

it('sends an adhoc notification via omnichannel', function () {
    // A notifiable that returns a PH mobile
    $user = new class {
        use Notifiable;
        public function routeNotificationFor($channel, $notification = null)
        {
            return '0917-000-1111';
        }
    };

    // Fire the notification
    $user->notify(new AdhocNotification('Test payload'));

    // Assert our service got called exactly once
    $this->sms->shouldHaveReceived('send')
        ->once()
        ->withArgs(function (string $recipient, string $content, ?string $from) {
            // E.164 formatting strips non-digits → "09170001111"
            expect($recipient)->toBe('639170001111');
            expect($content)->toBe('Test payload');
            // fallback sender from config
            expect($from)->toBe(config('omnichannel.default_sender_id'));
            return true;
        });
});
