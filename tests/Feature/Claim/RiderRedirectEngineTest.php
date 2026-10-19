<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use LBHurtado\EmiCore\Enums\PayoutStatus;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\PayCode\GeneratePayCode;
use LBHurtado\XChange\Actions\Redemption\RecordVoucherClaim;
use LBHurtado\XChange\ClaimWalkthrough\ClaimPreviewScenarioFactory;
use LBHurtado\XChange\Data\Redemption\SubmitPayCodeClaimResultData;
use LBHurtado\XChange\Http\Controllers\Web\Claim\ClaimRedirectController;
use LBHurtado\XChange\Http\Controllers\Web\Claim\ClaimSuccessPageController;
use LBHurtado\XChange\Http\Controllers\Web\Claim\RiderRedirectReceiptController;
use LBHurtado\XChange\Models\RiderRedirect;
use LBHurtado\XChange\Models\RiderRedirectEvent;
use LBHurtado\XChange\Models\VoucherClaim;
use LBHurtado\XChange\Services\Claim\ClaimExperienceCompiler;
use LBHurtado\XChange\Services\Claim\RiderRedirectEngine;
use LBHurtado\XChange\Services\Claim\RiderRedirectJournal;
use LBHurtado\XChange\Services\Commercial\ProvisionCommercialBaselines;
use LBHurtado\XChange\Tests\Fakes\User;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;
use LBHurtado\XJournal\Services\ExecutionJournalRecorder;

beforeEach(function () {
    Http::preventStrayRequests();
    config(['rider-redirect.enabled' => true, 'x-rider.redirects.allowed_hosts' => ['example.com']]);
    Route::middleware('web')->get('/x/claim/{code}/redirect', ClaimRedirectController::class)->name('x-change.claim.redirect');
    Route::post('/rider-receipts', RiderRedirectReceiptController::class);
});

function redirectVoucher(): Voucher
{
    return issueVoucher(validVoucherInstructions(overrides: ['rider' => ['url' => 'https://example.com/landing?campaign=one#start']]));
}

function redirectClaim(Voucher $voucher, string $status = 'succeeded'): VoucherClaim
{
    return VoucherClaim::query()->create(['voucher_id' => $voucher->getKey(), 'claim_number' => 1, 'claim_type' => 'withdraw', 'status' => $status, 'currency' => 'PHP', 'meta' => ['rider_redirect_session' => hash('sha256', str_repeat('s', 64))]]);
}

it('freezes and encrypts the destination and records one obligation', function () {
    $voucher = redirectVoucher();
    $engine = app(RiderRedirectEngine::class);
    $engine->commit($voucher);
    $redirect = RiderRedirect::query()->sole();
    expect($redirect->destination)->toBe('https://example.com/landing?campaign=one#start')
        ->and(DB::table('x_change_rider_redirects')->value('destination'))->not->toContain('example.com')
        ->and(RiderRedirectEvent::query()->count())->toBe(1);
    $metadata = $voucher->metadata;
    data_set($metadata, 'instructions.rider.url', 'https://other.example/changed');
    $voucher->forceFill(['metadata' => $metadata])->save();
    expect($redirect->fresh()->destination)->toBe('https://example.com/landing?campaign=one#start');
});

it('keeps legacy vouchers outside the new engine', function () {
    config(['rider-redirect.enabled' => false]);
    redirectVoucher();
    expect(RiderRedirect::query()->count())->toBe(0);
});

it('rejects an invalid paid instruction before resolving an issuer or charging', function () {
    app(GeneratePayCode::class)->handle(['amount' => 100, 'rider' => ['url' => 'javascript:alert(1)']]);
})->throws(ValidationException::class);

it('does not authorize handoff by knowing the code', function () {
    $voucher = redirectVoucher();
    redirectClaim($voucher);
    $this->get(route('x-change.claim.redirect', ['code' => $voucher->code]))->assertForbidden();
    expect(RiderRedirectEvent::query()->count())->toBe(2);
});

it('keeps pending and failed outcomes ineligible', function (string $status) {
    $voucher = redirectVoucher();
    redirectClaim($voucher, $status);
    $this->withSession(['rider_redirect_authorized.'.$voucher->getKey() => str_repeat('s', 64)])
        ->get(route('x-change.claim.redirect', ['code' => $voucher->code]))->assertStatus(409);
    expect(RiderRedirect::query()->sole()->eligible_at)->toBeNull();
})->with(['pending', 'failed', 'pending_review']);

it('navigates in the same tab through an Inertia location response and allows safe retries', function () {
    $voucher = redirectVoucher();
    $claim = redirectClaim($voucher, 'pending');
    $claim->update(['status' => 'succeeded']);
    $url = route('x-change.claim.redirect', ['code' => $voucher->code]);
    $this->withSession(['rider_redirect_authorized.'.$voucher->getKey() => str_repeat('s', 64)])
        ->withHeaders(['X-Inertia' => 'true'])->get($url)
        ->assertStatus(409)->assertHeader('X-Inertia-Location', 'https://example.com/landing?campaign=one#start')
        ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Referrer-Policy', 'no-referrer');
    $this->withHeaders(['X-Inertia' => 'true'])->get($url)->assertStatus(409);
    expect(VoucherClaim::query()->count())->toBe(1)
        ->and(RiderRedirectEvent::query()->where('event_type', 'claim.rider_redirect.redirect_prepared')->count())->toBe(2);
});

it('rolls eligibility and its evidence back with claim recording', function () {
    $voucher = redirectVoucher();
    try {
        DB::transaction(function () use ($voucher): void {
            redirectClaim($voucher);
            throw new RuntimeException('rollback');
        });
    } catch (RuntimeException) {
    }
    expect(RiderRedirect::query()->sole()->eligible_at)->toBeNull()
        ->and(RiderRedirectEvent::query()->count())->toBe(1);
});

it('keeps durable evidence when journal delivery fails and replays idempotently', function () {
    $voucher = redirectVoucher();
    redirectClaim($voucher);
    $real = app(ExecutionJournalRecorder::class);
    $this->mock(ExecutionJournalRecorder::class)->shouldReceive('record')->once()->andThrow(new RuntimeException('journal unavailable'));
    try {
        app(RiderRedirectJournal::class)->deliver();
    } catch (RuntimeException) {
    }
    expect(RiderRedirectEvent::query()->whereNull('delivered_at')->count())->toBe(2)
        ->and(RiderRedirectEvent::query()->oldest('id')->first()->delivery_attempts)->toBe(1)
        ->and(RiderRedirectEvent::query()->oldest('id')->first()->last_error_type)->toBe(RuntimeException::class);
    app()->instance(ExecutionJournalRecorder::class, $real);
    expect(app(RiderRedirectJournal::class)->deliver())->toBe(2);
    RiderRedirectEvent::query()->update(['delivered_at' => null]);
    expect(app(RiderRedirectJournal::class)->deliver())->toBe(2)
        ->and(ExecutionJournalEntry::query()->where('event_type', 'like', 'claim.rider_redirect.%')->count())->toBe(2);
});

it('accepts authenticated destination receipts once without claiming ordinary handoff is arrival', function () {
    config(['rider-redirect.receipt_keys' => ['example.com' => 'synthetic-test-key']]);
    $voucher = redirectVoucher();
    redirectClaim($voucher);
    $this->withSession(['rider_redirect_authorized.'.$voucher->getKey() => str_repeat('s', 64)])
        ->get(route('x-change.claim.redirect', ['code' => $voucher->code]))->assertStatus(303);
    $attempt = RiderRedirectEvent::query()->where('event_type', 'claim.rider_redirect.redirect_prepared')->sole()->attempt_reference;
    $body = json_encode(['reference' => $attempt, 'event' => 'page_initialized']);
    $timestamp = (string) time();
    $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_X_RIDER_TIMESTAMP' => $timestamp, 'HTTP_X_RIDER_SIGNATURE' => hash_hmac('sha256', $timestamp.'.'.$body, 'synthetic-test-key')];
    $this->call('POST', '/rider-receipts', [], [], [], $headers, $body)->assertOk();
    $this->call('POST', '/rider-receipts', [], [], [], $headers, $body)->assertOk();
    expect(RiderRedirectEvent::query()->where('event_type', 'claim.rider_redirect.page_initialized')->count())->toBe(1);
    $headers['HTTP_X_RIDER_SIGNATURE'] = 'forged';
    $this->call('POST', '/rider-receipts', [], [], [], $headers, $body)->assertForbidden();
    $headers['HTTP_X_RIDER_TIMESTAMP'] = (string) (time() - 1000);
    $headers['HTTP_X_RIDER_SIGNATURE'] = hash_hmac('sha256', $headers['HTTP_X_RIDER_TIMESTAMP'].'.'.$body, 'synthetic-test-key');
    $this->call('POST', '/rider-receipts', [], [], [], $headers, $body)->assertForbidden();
});

it('accepts the engine successful terminal statuses', function (string $status) {
    $voucher = redirectVoucher();
    redirectClaim($voucher, $status);
    expect(RiderRedirect::query()->sole()->eligible_at)->not->toBeNull();
})->with(['succeeded', 'withdrawn', 'redeemed', 'settled']);

it('recovers through the success endpoint without executing the claim again', function () {
    Route::middleware('web')->get('/x/claim/{code}/success', ClaimSuccessPageController::class)->name('x-change.claim.success');
    $voucher = redirectVoucher();
    redirectClaim($voucher);
    $this->withSession(['rider_redirect_authorized.'.$voucher->getKey() => str_repeat('s', 64)])->withHeaders(['X-Inertia' => 'true'])
        ->get(route('x-change.claim.success', ['code' => $voucher->code]))->assertStatus(409)
        ->assertHeader('X-Inertia-Location', route('x-change.claim.redirect', ['code' => $voucher->code]));
    expect(VoucherClaim::query()->count())->toBe(1);
});

it('does not redirect while reconciliation is still required', function () {
    $voucher = redirectVoucher();
    redirectClaim($voucher);
    $metadata = $voucher->metadata;
    data_set($metadata, 'disbursement.requires_reconciliation', true);
    $voucher->forceFill(['metadata' => $metadata])->save();
    $this->withSession(['rider_redirect_authorized.'.$voucher->getKey() => str_repeat('s', 64)])
        ->get(route('x-change.claim.redirect', ['code' => $voucher->code]))->assertStatus(409);
});

it('proves issuance claim handoff and journal through the lifecycle scenario runner', function () {
    config(['x-change.lifecycle.defaults.user_model' => User::class,
        'x-change.lifecycle.defaults.system_user_email' => 'system@example.test',
        'x-change.lifecycle.defaults.test_user_email' => 'lester@hurtado.ph',
        'x-change.lifecycle.defaults.test_user_mobile' => '09173011987']);
    app(ProvisionCommercialBaselines::class)->provision('rider-lifecycle-fixture:v1');
    Artisan::call('xchange:lifecycle:prepare', ['--seed' => true]);
    $this->fakePayoutProvider()->nextStatus = PayoutStatus::COMPLETED;
    config(['x-change.lifecycle.scenarios.basic_cash.rider.url' => 'https://example.com/landing']);
    $this->artisan('xchange:lifecycle:run', ['scenario' => 'basic_cash', '--timeout' => 1, '--poll' => 1, '--accept-pending' => true])->assertExitCode(0);
    $redirect = RiderRedirect::query()->sole();
    expect($redirect->eligible_at)->not->toBeNull();
    $voucher = Voucher::query()->findOrFail($redirect->voucher_id);
    $claim = VoucherClaim::query()->where('voucher_id', $voucher->getKey())->latest('id')->firstOrFail();
    $claim->update(['meta' => array_merge((array) $claim->meta, ['rider_redirect_session' => hash('sha256', str_repeat('s', 64))])]);
    $this->withSession(['rider_redirect_authorized.'.$voucher->getKey() => str_repeat('s', 64)])->get(route('x-change.claim.redirect', ['code' => $voucher->code]))->assertStatus(303)->assertRedirect('https://example.com/landing');
    expect(app(RiderRedirectJournal::class)->deliver())->toBe(4);
});

it('does not use another claimants success to authorize a redirect', function () {
    $voucher = redirectVoucher();
    redirectClaim($voucher);
    $this->withSession(['rider_redirect_authorized.'.$voucher->getKey() => str_repeat('x', 64)])
        ->get(route('x-change.claim.redirect', ['code' => $voucher->code]))->assertStatus(409);
});

it('does not mutate the issued destination through the model', function () {
    redirectVoucher();
    RiderRedirect::query()->sole()->update(['destination' => 'https://example.com/changed']);
})->throws(LogicException::class);

it('previews the backend handoff without executing it or showing a separate success countdown', function () {
    Http::fake(['*' => Http::response('', 200)]);
    $instructions = VoucherInstructionsData::from(validVoucherInstructions(overrides: ['rider' => ['url' => 'https://example.com/landing']]));
    $scenario = app(ClaimPreviewScenarioFactory::class)->fromInstructions($instructions);
    $keys = array_column($scenario['checkpoints'], 'key');
    expect($keys)->toContain('confirmation', 'rider-url')->not->toContain('claim-success-rider-message', 'rider-redirect-countdown');
    expect(RiderRedirect::query()->count())->toBe(0);
});

it('compiles backend ownership from the frozen issued destination', function () {
    $voucher = redirectVoucher();
    $experience = app(ClaimExperienceCompiler::class)->compile($voucher)->toArray();
    expect(data_get($experience, 'diagnostics.redirect_owner'))->toBe('backend')
        ->and(data_get($experience, 'options.show_redirect_countdown'))->toBeFalse();
});

it('blocks an unresolved provider state even if a broad claim status was successful', function (string $status) {
    $voucher = redirectVoucher();
    redirectClaim($voucher);
    $metadata = $voucher->metadata;
    data_set($metadata, 'disbursement.status', $status);
    $voucher->forceFill(['metadata' => $metadata])->save();
    $this->withSession(['rider_redirect_authorized.'.$voucher->getKey() => str_repeat('s', 64)])
        ->get(route('x-change.claim.redirect', ['code' => $voucher->code]))->assertStatus(409);
})->with(['pending', 'processing', 'failed', 'rejected']);

it('does not reuse an earlier success after this session has a newer failed claim', function () {
    $voucher = redirectVoucher();
    redirectClaim($voucher);
    VoucherClaim::query()->create(['voucher_id' => $voucher->getKey(), 'claim_number' => 2, 'claim_type' => 'withdraw', 'status' => 'failed', 'currency' => 'PHP', 'meta' => ['rider_redirect_session' => hash('sha256', str_repeat('s', 64))]]);
    $this->withSession(['rider_redirect_authorized.'.$voucher->getKey() => str_repeat('s', 64)])
        ->get(route('x-change.claim.redirect', ['code' => $voucher->code]))->assertStatus(409);
});

it('binds the real claim recorder to the submitting browser session before navigation', function () {
    Queue::fake();
    $voucher = redirectVoucher();
    Route::middleware('web')->post('/synthetic-record-claim', function (Request $request) use ($voucher) {
        app(RiderRedirectEngine::class)->authorizeSession($voucher, $request);
        app(RecordVoucherClaim::class)->handle($voucher,
            new SubmitPayCodeClaimResultData(
                voucher_code: $voucher->code, claim_type: 'withdraw', claimed: true, status: 'succeeded',
                requested_amount: 100, disbursed_amount: 100, currency: 'PHP', remaining_balance: 0,
                fully_claimed: true, disbursement: [], messages: [],
            ));

        return redirect()->route('x-change.claim.redirect', ['code' => $voucher->code]);
    });
    $this->post('/synthetic-record-claim')->assertRedirect(route('x-change.claim.redirect', ['code' => $voucher->code]));
    expect(strlen((string) data_get(VoucherClaim::query()->sole()->meta, 'rider_redirect_session')))->toBe(64);
    $this->get(route('x-change.claim.redirect', ['code' => $voucher->code]))->assertStatus(303);
});
