<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Payment;

use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Enums\PaymentAttemptStatus;
use LBHurtado\XChange\Enums\PaymentVerificationTrigger;
use LBHurtado\XChange\Models\PaymentAttempt;

class TransitionPaymentAttempt
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $metadata
     */
    public function handle(
        PaymentAttempt $attempt,
        PaymentAttemptStatus $status,
        string $eventType,
        PaymentVerificationTrigger $trigger,
        array $attributes = [],
        array $metadata = [],
    ): PaymentAttempt {
        return DB::transaction(function () use (
            $attempt,
            $status,
            $eventType,
            $trigger,
            $attributes,
            $metadata,
        ): PaymentAttempt {
            $locked = PaymentAttempt::query()->lockForUpdate()->findOrFail($attempt->getKey());
            $from = $locked->status;
            $nextVersion = $locked->version + 1;

            $locked->forceFill([
                ...$attributes,
                'status' => $status,
                'version' => $nextVersion,
            ])->saveQuietly();

            $locked->events()->create([
                'sequence' => $nextVersion,
                'event_type' => $eventType,
                'from_status' => $from,
                'to_status' => $status,
                'trigger' => $trigger->value,
                'evidence_reference' => isset($attributes['matched_observation_id'])
                    ? 'provider-observation:'.$attributes['matched_observation_id']
                    : null,
                'metadata' => $metadata,
                'occurred_at' => now(),
            ]);

            return $locked->refresh()->load(['events', 'voucher']);
        }, 3);
    }
}
