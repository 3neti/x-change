<?php

namespace App\Pipelines\RedeemedVoucher;

use Spatie\LaravelData\Exceptions\InvalidDataClass;
use App\Notifications\SendFeedbacksNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Log;
use Closure;

/**
 * SendFeedbacks is a pipeline stage that sends post-redemption
 * feedback notifications based on the configured feedback channels
 * (e.g., email, mobile) present in the voucher's instructions.
 *
 * The instructions are expected to be stored in:
 *   $voucher->getData()->instructions->feedback
 *
 * Example structure:
 *   ['email' => 'john@doe.com', 'mobile' => '09171234567']
 */
class SendFeedbacks
{
    /**
     * Send feedback notifications via the resolved channels.
     *
     * @param \LBHurtado\Voucher\Models\Voucher $voucher
     * @param \Closure $next
     * @return mixed
     * @throws InvalidDataClass
     */
    public function handle($voucher, Closure $next)
    {
        $rawFeedbacks = $voucher->getData()->instructions->feedback->toArray() ?? [];

        $feedbacks = array_filter($rawFeedbacks, fn($value) => !empty($value));
        $routes = $this->getRoutesFromFeedbacks($feedbacks);

        Log::info('[SendFeedbacks] Feedback routes resolved', [
            'voucher' => $voucher->code,
            'routes' => $routes,
        ]);

        if (empty($routes)) {
            Log::info('[SendFeedbacks] No valid feedback routes found; skipping notification.', [
                'voucher' => $voucher->code,
            ]);
            return $next($voucher);
        }

        Notification::routes($routes)->notify(new SendFeedbacksNotification($voucher->code));

        Log::info('[SendFeedbacks] Feedback notification sent', [
            'voucher' => $voucher->code,
            'channels' => array_keys($routes),
        ]);

        return $next($voucher);
    }

    /**
     * Convert feedback map to route format for Notification::routes()
     *
     * @param  array  $feedbacks
     * @return array  ['mail' => ..., 'engage_spark' => ...]
     */
    private function getRoutesFromFeedbacks(array $feedbacks): array
    {
        $routes = [];

        foreach ($feedbacks as $key => $value) {
            match ($key) {
                'email' => $routes['mail'] = $value,
                'mobile' => $routes['engage_spark'] = $value,
                default => null, // skip unrecognized keys
            };
        }

        return $routes;
    }
}
