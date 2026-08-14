<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use LBHurtado\Voucher\Enums\VoucherState;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\Claim\ClaimApprovalStatusResolver;
use LBHurtado\XChange\Contracts\Claim\ClaimSurfaceResolverContract;
use LBHurtado\XChange\Data\Claims\ApprovalStatusData;
use LBHurtado\XChange\Enums\ClaimEvidenceKind;
use LBHurtado\XChange\Enums\ClaimEvidenceStatus;
use LBHurtado\XChange\Models\VoucherClaim;
use LBHurtado\XChange\Models\VoucherClaimEvidence;
use LBHurtado\XChange\Tests\Fakes\User;

function claimSurfaceResolver(): ClaimSurfaceResolverContract
{
    return app(ClaimSurfaceResolverContract::class);
}

function otherAuthenticatedUser(): User
{
    return User::query()->create([
        'name' => 'Unrelated User',
        'email' => 'unrelated+'.Str::uuid().'@example.test',
        'password' => Hash::make('password'),
    ]);
}

/**
 * @return array{claim: VoucherClaim}
 */
function recordClaimWithEvidence(Voucher $voucher, array $requirementKeys = ['mobile', 'selfie']): array
{
    $claim = VoucherClaim::query()->create([
        'voucher_id' => $voucher->getKey(),
        'claim_number' => 1,
        'claim_type' => 'withdraw',
        'status' => 'succeeded',
        'requested_amount_minor' => 10_000,
        'disbursed_amount_minor' => 10_000,
        'remaining_balance_minor' => 0,
        'currency' => 'PHP',
        'bank_code' => 'GXCHPHM2XXX',
        'account_number_masked' => '*******1987',
        'completed_at' => now(),
    ]);

    foreach ($requirementKeys as $key) {
        VoucherClaimEvidence::query()->create([
            'voucher_claim_id' => $claim->getKey(),
            'voucher_id' => $voucher->getKey(),
            'requirement_key' => $key,
            'kind' => $key === 'selfie' ? ClaimEvidenceKind::Image : ClaimEvidenceKind::Text,
            'status' => ClaimEvidenceStatus::Captured,
            'summary' => $key === 'mobile' ? '•••• 1987' : ucfirst($key).' captured',
            'captured_at' => now(),
        ]);
    }

    $voucher->forceFill(['redeemed_at' => now()])->save();

    return ['claim' => $claim];
}

it('gives a guest a public preview surface for a claimable Pay Code', function () {
    $issuer = actingAsTestUser();
    $voucher = issueVoucher(validVoucherInstructions(100));
    auth()->logout();

    $surface = claimSurfaceResolver()->resolve($voucher, null);

    expect($surface->visibility)->toBe('public_preview')
        ->and($surface->viewer->role)->toBe('guest')
        ->and($surface->viewer->authenticated)->toBeFalse()
        ->and($surface->state->key)->toBe('active')
        ->and(collect($surface->components)->pluck('type'))->toContain('xray_preview')
        ->and(collect($surface->components)->pluck('type'))->not->toContain('claim_requirement_summary');
});

it('gives the issuer an issuer console surface once their Pay Code has been claimed', function () {
    $issuer = actingAsTestUser();
    $voucher = issueVoucher(validVoucherInstructions(100, 'INSTAPAY', [
        'inputs' => ['fields' => ['mobile', 'selfie']],
    ]));
    recordClaimWithEvidence($voucher);

    $surface = claimSurfaceResolver()->resolve($voucher, $issuer);

    expect($surface->visibility)->toBe('issuer_console')
        ->and($surface->viewer->role)->toBe('issuer')
        ->and($surface->headline)->toBe('Your Pay Code was claimed');
});

it('includes the claim requirement summary component for a claimed Pay Code issuer view', function () {
    $issuer = actingAsTestUser();
    $voucher = issueVoucher(validVoucherInstructions(100, 'INSTAPAY', [
        'inputs' => ['fields' => ['mobile', 'selfie']],
    ]));
    recordClaimWithEvidence($voucher);

    $surface = claimSurfaceResolver()->resolve($voucher, $issuer);

    $summary = collect($surface->components)->firstWhere('type', 'claim_requirement_summary');
    $items = collect($summary['props']['items'])->keyBy('key');

    expect($summary)->not->toBeNull()
        ->and($items->has('mobile'))->toBeTrue()
        ->and($items->has('destination_account'))->toBeTrue()
        ->and($items->has('selfie'))->toBeTrue()
        ->and($items['mobile']['status'])->toBe('completed')
        ->and($items['destination_account']['status'])->toBe('completed')
        ->and($items['selfie']['status'])->toBe('captured');

    // Never a raw value -- only status/tone/label.
    foreach ($items as $item) {
        expect($item)->toHaveKeys(['key', 'label', 'status', 'tone', 'description'])
            ->and($item['description'])->toBeNull();
    }
});

it('includes an open pay code action for a claimed Pay Code issuer view', function () {
    $issuer = actingAsTestUser();
    $voucher = issueVoucher(validVoucherInstructions(100));
    recordClaimWithEvidence($voucher, []);

    $surface = claimSurfaceResolver()->resolve($voucher, $issuer);

    $action = collect($surface->actions)->firstWhere('key', 'open_pay_code');

    expect($action)->not->toBeNull()
        ->and($action['method'])->toBe('get')
        ->and($action['href'])->toContain('/x/cockpit/pay-codes/'.$voucher->code)
        ->and($action['href'])->not->toContain('/x/pay-codes/'.$voucher->code);
});

it('includes an approve payout action when the claim is pending issuer approval', function () {
    $issuer = actingAsTestUser();
    $voucher = issueVoucher(validVoucherInstructions(100));
    recordClaimWithEvidence($voucher, []);

    test()->mock(ClaimApprovalStatusResolver::class, function ($mock) use ($voucher) {
        $mock->shouldReceive('resolve')->andReturn(new ApprovalStatusData(
            status: 'approval_required',
            voucher_code: (string) $voucher->code,
            messages: ['Payout OTP approval required.'],
            provider: 'paynamics',
            authorization_type: 'otp',
            reference_id: $voucher->code.'-09173011987',
            otp_required: true,
            message: 'Paynamics payout OTP is pending.',
        ));
    });

    $surface = claimSurfaceResolver()->resolve($voucher, $issuer);

    $approveAction = collect($surface->actions)->firstWhere('key', 'approve_payout');
    $approvalItem = collect(
        collect($surface->components)->firstWhere('type', 'claim_requirement_summary')['props']['items']
    )->firstWhere('key', 'approval');

    expect($approveAction)->not->toBeNull()
        ->and($approveAction['variant'])->toBe('primary')
        ->and($approvalItem)->not->toBeNull()
        ->and($approvalItem['status'])->toBe('pending');
});

it('does not give an unrelated authenticated user the requirement summary or issuer console', function () {
    $issuer = actingAsTestUser();
    $voucher = issueVoucher(validVoucherInstructions(100));
    recordClaimWithEvidence($voucher);
    $other = otherAuthenticatedUser();

    $surface = claimSurfaceResolver()->resolve($voucher, $other);

    expect($surface->viewer->role)->toBe('other_authenticated')
        ->and($surface->visibility)->toBe('public_preview')
        ->and(collect($surface->components)->pluck('type'))->not->toContain('claim_requirement_summary');
});

it('does not give a guest the requirement summary even for a Pay Code the guest happened to claim', function () {
    $issuer = actingAsTestUser();
    $voucher = issueVoucher(validVoucherInstructions(100));
    recordClaimWithEvidence($voucher);
    auth()->logout();

    $surface = claimSurfaceResolver()->resolve($voucher, null);

    expect($surface->viewer->role)->toBe('guest')
        ->and(collect($surface->components)->pluck('type'))->not->toContain('claim_requirement_summary');
});

it('maps a cancelled Pay Code to an outcome panel', function () {
    $issuer = actingAsTestUser();
    $voucher = issueVoucher(validVoucherInstructions(100));
    $voucher->forceFill(['state' => VoucherState::CANCELLED, 'closed_at' => now()])->save();
    auth()->logout();

    $surface = claimSurfaceResolver()->resolve($voucher, null);

    expect($surface->state->key)->toBe('cancelled')
        ->and($surface->state->terminal)->toBeTrue()
        ->and(collect($surface->components)->pluck('type'))->toContain('outcome_panel');
});

it('maps an expired Pay Code to an outcome panel', function () {
    $issuer = actingAsTestUser();
    $voucher = issueVoucher(validVoucherInstructions(100));
    $voucher->forceFill(['expires_at' => now()->subMinute()])->save();
    auth()->logout();

    $surface = claimSurfaceResolver()->resolve($voucher, null);

    expect($surface->state->key)->toBe('expired')
        ->and($surface->state->terminal)->toBeTrue()
        ->and(collect($surface->components)->pluck('type'))->toContain('outcome_panel');
});

it('maps a redeemed Pay Code to an outcome panel', function () {
    $issuer = actingAsTestUser();
    $voucher = issueVoucher(validVoucherInstructions(100));
    $voucher->forceFill(['redeemed_at' => now()])->save();
    auth()->logout();

    $surface = claimSurfaceResolver()->resolve($voucher, null);

    expect($surface->state->key)->toBe('redeemed')
        ->and($surface->state->terminal)->toBeTrue()
        ->and(collect($surface->components)->pluck('type'))->toContain('outcome_panel');
});

it('adds a static claim experience summary for a redeemed rider Pay Code', function () {
    $issuer = actingAsTestUser();
    Cache::clear();
    Http::preventStrayRequests();
    Http::fake([
        'https://open.spotify.com/oembed*' => Http::response(
            [
                'title' => 'An Example Track',
                'provider_name' => 'Spotify',
                'thumbnail_url' => 'https://i.scdn.co/image/example-artwork',
            ],
            200,
            ['Content-Type' => 'application/json'],
        ),
        'https://i.scdn.co/image/example-artwork' => Http::response(
            'fake-jpeg-bytes',
            200,
            ['Content-Type' => 'image/jpeg'],
        ),
    ]);

    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'rider' => [
            'message' => 'Thank you for riding with us.',
            'url' => 'https://open.spotify.com/track/6CKoWCWAqEVWVjpeoJXyNH?si=tracking-token',
            'redirect_timeout' => 5,
            'splash' => '<h1>Before you claim</h1>',
            'splash_timeout' => 3,
            'og_source' => 'splash',
            'stamp' => [
                'version' => 2,
                'source' => 'splash',
                'artwork_source' => 'splash',
                'copy_source' => 'message',
            ],
        ],
    ]));
    $voucher->forceFill(['redeemed_at' => now()])->save();
    auth()->logout();

    $surface = claimSurfaceResolver()->resolve($voucher, null);
    $summary = collect($surface->components)->firstWhere('type', 'claim_experience_summary');

    expect($summary)->not->toBeNull()
        ->and(data_get($summary, 'props.message.content'))->toContain('Thank you for riding with us.')
        ->and(data_get($summary, 'props.splash.content'))->toContain('Before you claim')
        ->and(data_get($summary, 'props.redirect.url'))->toBe('https://open.spotify.com/track/6CKoWCWAqEVWVjpeoJXyNH?si=tracking-token')
        ->and(data_get($summary, 'props.og_meta.title'))->toBe('An Example Track')
        ->and(data_get($summary, 'props.og_meta.description'))->toBe('Spotify')
        ->and(data_get($summary, 'props.og_meta.image_url'))->toBe('data:image/jpeg;base64,'.base64_encode('fake-jpeg-bytes'))
        ->and(data_get($summary, 'props.og_meta.public_image_url'))->toBe('https://i.scdn.co/image/example-artwork')
        ->and(data_get($summary, 'props.og_meta.amount_label'))->toBeNull()
        ->and(data_get($summary, 'props.og_meta.message_preview'))->toBeNull()
        ->and(data_get($summary, 'props.options.static_preview'))->toBeTrue()
        ->and(data_get($summary, 'props.options.disable_auto_redirect'))->toBeTrue();

    Http::assertSentCount(2);
});

it('adds claim requirements and claim experience for the issuer review surface', function () {
    $issuer = actingAsTestUser();
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'inputs' => ['fields' => ['mobile', 'selfie', 'signature']],
        'rider' => [
            'message' => 'Claim complete message.',
            'url' => 'https://example.test/after',
            'splash' => '<section>Issuer configured splash</section>',
        ],
    ]));
    recordClaimWithEvidence($voucher, ['mobile', 'selfie', 'signature']);

    $surface = claimSurfaceResolver()->resolve($voucher, $issuer);
    $componentTypes = collect($surface->components)->pluck('type');

    expect($surface->visibility)->toBe('issuer_console')
        ->and($componentTypes)->toContain('claim_requirement_summary')
        ->and($componentTypes)->toContain('claim_experience_summary');
});

it('does not add the static claim experience summary for an active claimable Pay Code', function () {
    $issuer = actingAsTestUser();
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'rider' => [
            'message' => 'Claim complete message.',
            'url' => 'https://example.test/after',
            'splash' => '<section>Issuer configured splash</section>',
        ],
    ]));
    auth()->logout();

    $surface = claimSurfaceResolver()->resolve($voucher, null);

    expect($surface->state->can_claim)->toBeTrue()
        ->and(collect($surface->components)->pluck('type'))->not->toContain('claim_experience_summary');
});

it('keeps a partially claimed Pay Code with remaining balance claimable', function () {
    $issuer = actingAsTestUser();
    $voucher = issueVoucher(validVoucherInstructions(1_000, 'INSTAPAY', [
        'cash' => [
            'slice_mode' => 'open',
            'max_slices' => 5,
            'min_withdrawal' => 100,
        ],
    ]));

    VoucherClaim::query()->create([
        'voucher_id' => $voucher->getKey(),
        'claim_number' => 1,
        'claim_type' => 'withdraw',
        'status' => 'succeeded',
        'requested_amount_minor' => 20_000,
        'disbursed_amount_minor' => 20_000,
        'remaining_balance_minor' => 80_000,
        'currency' => 'PHP',
        'completed_at' => now(),
    ]);
    auth()->logout();

    $surface = claimSurfaceResolver()->resolve($voucher, null);

    expect($surface->state->key)->toBe('partially_claimed')
        ->and($surface->state->can_claim)->toBeTrue()
        ->and($surface->state->terminal)->toBeFalse();
});

it('renders a partially claimed Pay Code with no remaining balance as an outcome panel', function () {
    $issuer = actingAsTestUser();
    $voucher = issueVoucher(validVoucherInstructions(1_000, 'INSTAPAY', [
        'cash' => [
            'slice_mode' => 'open',
            'max_slices' => 5,
            'min_withdrawal' => 100,
        ],
    ]));
    $voucher->forceFill(['expires_at' => now()->subMinute()])->save();

    VoucherClaim::query()->create([
        'voucher_id' => $voucher->getKey(),
        'claim_number' => 1,
        'claim_type' => 'withdraw',
        'status' => 'succeeded',
        'requested_amount_minor' => 20_000,
        'disbursed_amount_minor' => 20_000,
        'remaining_balance_minor' => 80_000,
        'currency' => 'PHP',
        'completed_at' => now(),
    ]);
    auth()->logout();

    $surface = claimSurfaceResolver()->resolve($voucher, null);

    expect($surface->state->key)->toBe('partially_claimed')
        ->and($surface->state->can_claim)->toBeFalse()
        ->and(collect($surface->components)->pluck('type'))->toContain('outcome_panel');
});
