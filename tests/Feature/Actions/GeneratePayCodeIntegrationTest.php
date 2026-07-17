<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\PayCode\GeneratePayCode;
use LBHurtado\XChange\Contracts\VoucherLifecycleServiceContract;
use LBHurtado\XChange\Data\DebitData;
use LBHurtado\XChange\Data\PayCode\GeneratePayCodeResultData;
use LBHurtado\XChange\Exceptions\InsufficientWalletBalance;

it('generates a pay code end to end and debits the issuer wallet', function () {
    $user = actingAsTestUser(1_000_000);

    config()->set('app.url', 'https://example.test');

    $wallet = $user->wallet()->where('slug', 'platform')->first();
    expect($wallet)->not->toBeNull();

    $balanceBefore = (float) $wallet->balance;

    $payload = array_merge(validPayCodePayload(), [
        'issuer_id' => $user->id,
    ]);

    $action = app(GeneratePayCode::class);

    $result = $action->handle($payload);

    expect($result)->toBeInstanceOf(GeneratePayCodeResultData::class);

    expect($result->voucher_id)->not->toBeNull();
    expect($result->code)->toBeString();
    expect($result->amount)->toBe(100.0);
    expect($result->currency)->toBe('PHP');

    expect($result->issuer->id)->toBe($user->id);

    expect($result->cost->currency)->toBe('PHP');
    expect($result->cost->total)->toBeGreaterThan(0);

    expect((float) $result->wallet['balance_before'])->toBe($balanceBefore);
    expect((float) $result->wallet['balance_after'])->toBeLessThan($balanceBefore);

    expect($result->debit->id)->not->toBeNull();

    expect($result->links->redeem)->toContain($result->code);
    expect($result->links->redeem_path)->toContain($result->code);
    expect($result->links->redeem)->toBe("https://example.test/x/claim/{$result->code}/experience");
    expect($result->links->redeem_path)->toBe("/x/claim/{$result->code}/experience");

    $wallet->refresh();

    expect((float) $result->wallet['balance_before'])->toBe($balanceBefore);
    expect((float) $result->wallet['balance_after'])->toBeLessThan($balanceBefore);
    expect((float) $wallet->balance)->toBe((float) $result->wallet['balance_after']);

    expect($result->debit)->toBeInstanceOf(DebitData::class);
    expect($result->debit)->toHaveKey('id');

    $voucher = Voucher::query()->find($result->voucher_id);

    expect($voucher)->not->toBeNull();
    expect($voucher?->code)->toBe($result->code);
    expect($voucher?->instructions)->not->toBeNull();
    expect(data_get($voucher?->instructions, 'cash.amount'))->toBe(100.0);
});

it('does not emit the brick math float deprecation during voucher cash persistence', function () {
    $user = actingAsTestUser(1_000_000);

    $payload = array_merge(validPayCodePayload(25.0), [
        'issuer_id' => $user->id,
    ]);

    $deprecations = [];

    set_error_handler(function (int $severity, string $message, string $file, int $line) use (&$deprecations): bool {
        if (! str_contains($message, 'Passing floats to BigNumber::of()')) {
            return false;
        }

        $deprecations[] = [
            'severity' => $severity,
            'message' => $message,
            'file' => $file,
            'line' => $line,
            'trace_files' => collect(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS))
                ->pluck('file')
                ->filter()
                ->values()
                ->all(),
        ];

        return true;
    });

    try {
        $result = app(GeneratePayCode::class)->handle($payload);
    } finally {
        restore_error_handler();
    }

    expect($result)->toBeInstanceOf(GeneratePayCodeResultData::class)
        ->and($result->amount)->toBe(25.0)
        ->and($deprecations)->toBeEmpty();
});

it('fails end to end when issuer wallet cannot afford pay code generation', function () {
    $user = actingAsTestUser(0);

    $payload = array_merge(validPayCodePayload(100.0, 'INSTAPAY', ['inputs' => ['fields' => ['selfie']]]), [
        'issuer_id' => $user->id,
    ]);

    $action = app(GeneratePayCode::class);

    expect(fn () => $action->handle($payload))
        ->toThrow(InsufficientWalletBalance::class);
});

it('characterizes that cancellation does not credit issuer wallet funds today', function () {
    $user = actingAsTestUser(1_000_000);
    $wallet = $user->wallet()->where('slug', 'platform')->firstOrFail();

    $result = app(GeneratePayCode::class)->handle(array_merge(validPayCodePayload(25.0), [
        'issuer_id' => $user->id,
    ]));

    $wallet->refresh();
    $afterIssuance = (int) $wallet->balance;

    app(VoucherLifecycleServiceContract::class)->cancel((string) $result->voucher_id, [
        'reason' => 'money semantics characterization',
    ]);

    $wallet->refresh();

    expect((int) $wallet->balance)->toBe($afterIssuance);
});

it('characterizes that expiry does not credit issuer wallet funds today', function () {
    $user = actingAsTestUser(1_000_000);
    $wallet = $user->wallet()->where('slug', 'platform')->firstOrFail();

    $result = app(GeneratePayCode::class)->handle(array_merge(validPayCodePayload(25.0), [
        'issuer_id' => $user->id,
    ]));

    $wallet->refresh();
    $afterIssuance = (int) $wallet->balance;

    Voucher::query()
        ->whereKey($result->voucher_id)
        ->update(['expires_at' => now()->subMinute()]);

    $wallet->refresh();

    expect((int) $wallet->balance)->toBe($afterIssuance);
});
