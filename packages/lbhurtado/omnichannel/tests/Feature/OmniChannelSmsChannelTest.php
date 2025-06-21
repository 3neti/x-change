<?php

use LBHurtado\OmniChannel\Notifications\OmniChannelSmsChannel;
use LBHurtado\OmniChannel\Notifications\OmniChannelSmsMessage;
use LBHurtado\OmniChannel\Services\OmniChannelService;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Notifiable;

beforeEach(function () {
    // Bind a spy so we can assert send(...) was called.
    $this->sms = Mockery::spy(OmniChannelService::class);
    $this->app->instance(OmniChannelService::class, $this->sms);

    // Make sure the channel is resolved properly
    $this->channel = $this->app->make(OmniChannelSmsChannel::class);
});

it('sends a simple string payload', function () {
    $user = new class {
        use Notifiable;
        public function routeNotificationFor($channel, $notification = null)
        {
            return '0917 123 4567';
        }
    };

    $notification = new class extends Notification {
        public function via($notifiable): array
        {
            return ['omnichannel'];
        }

        public function toOmnichannel($notifiable)
        {
            // returning a simple string
            return 'Hello World';
        }
    };

    // Fire it off
    $user->notify($notification);

    // Assert our spy was called once.
    $this->sms->shouldHaveReceived('send')
        ->once()
        ->withArgs(function (string $recipient, string $content, ?string $from) {
            // recipient normalized to E.164 digits-only
            expect($recipient)->toBe('639171234567');
            expect($content)->toBe('Hello World');
            // from should fall back to config default
            expect($from)->toBe(config('omnichannel.default_sender_id'));
            return true;
        });
});

it('sends a rich OmniChannelSmsMessage payload', function () {
    $user = new class {
        use Notifiable;
        public function routeNotificationFor($channel, $notification = null)
        {
            return '+63 917 765 4321';
        }
    };

    $notification = new class extends Notification {
        public function via($notifiable): array
        {
            return ['omnichannel'];
        }

        public function toOmnichannel($notifiable)
        {
            // returning a DTO, including a custom `from`
            return new OmniChannelSmsMessage(
                content: 'Balance is low',
                from:    'MYBUSINESS'
            );
        }
    };

    $user->notify($notification);

    $this->sms->shouldHaveReceived('send')
        ->once()
        ->withArgs(function (string $recipient, string $content, string $from) {
            // strip non-digits from +63… → "639177654321"
            expect($recipient)->toBe('639177654321');
            expect($content)->toBe('Balance is low');
            expect($from)->toBe('MYBUSINESS');
            return true;
        });
});
