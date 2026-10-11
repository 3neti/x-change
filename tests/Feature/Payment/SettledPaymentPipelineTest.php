<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;
use LBHurtado\XChange\Actions\Payment\RecordSettledPayment;
use LBHurtado\XChange\Actions\Payment\RunSettledPaymentPipeline;
use LBHurtado\XChange\Jobs\Payment\ProcessSettledPaymentJob;
use LBHurtado\XChange\Models\SettledPaymentProcess;

it('records a settled payment once and snapshots the SMS policy', function () {
    Queue::fake();
    config()->set('x-change.payment_feedback.sms.enabled', true);
    config()->set('x-change.payment_feedback.sms.flows.top_up', false);

    $recorder = app(RecordSettledPayment::class);
    $first = $recorder->handle('funding_settlement', 912, 427, 'top_up', 5000);
    config()->set('x-change.payment_feedback.sms.flows.top_up', true);
    $second = $recorder->handle('funding_settlement', 912, 427, 'top_up', 5000);

    expect($first->getKey())->toBe($second->getKey())
        ->and($first->sms_enabled_snapshot)->toBeFalse()
        ->and(SettledPaymentProcess::query()->count())->toBe(1);
    Queue::assertPushed(ProcessSettledPaymentJob::class, 1);
});

it('runs configured settled payment pipes once', function () {
    $process = SettledPaymentProcess::query()->create([
        'payment_type' => 'funding_settlement',
        'payment_id' => 913,
        'observation_id' => 428,
        'flow' => 'top_up',
        'gross_amount_minor' => 5000,
        'sms_enabled_snapshot' => false,
    ]);
    $calls = 0;
    config()->set('x-change.payment_feedback.pipes', [function (SettledPaymentProcess $payment, Closure $next) use (&$calls): mixed {
        $calls++;

        return $next($payment);
    }]);

    app(RunSettledPaymentPipeline::class)->handle($process->getKey());
    app(RunSettledPaymentPipeline::class)->handle($process->getKey());

    expect($calls)->toBe(1)
        ->and($process->fresh()->status)->toBe('completed');
});

it('requeues only pending payment processes for recovery', function () {
    Queue::fake();
    foreach (['pending', 'completed'] as $offset => $status) {
        SettledPaymentProcess::query()->create([
            'payment_type' => 'funding_settlement',
            'payment_id' => 920 + $offset,
            'observation_id' => 430 + $offset,
            'flow' => 'top_up',
            'gross_amount_minor' => 5000,
            'sms_enabled_snapshot' => false,
            'status' => $status,
        ]);
    }

    $this->artisan('xchange:payment-feedback:replay')->assertSuccessful();

    Queue::assertPushed(ProcessSettledPaymentJob::class, 1);
});
