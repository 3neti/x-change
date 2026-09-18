<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Leads\CreateLeadCampaign;
use LBHurtado\XChange\Actions\PayCode\GeneratePayCode;
use LBHurtado\XChange\Actions\Payment\TransitionPaymentAttempt;
use LBHurtado\XChange\Actions\Redemption\SubmitWebPayCodeClaim;
use LBHurtado\XChange\Contracts\SettlementEnvelopeReadinessContract;
use LBHurtado\XChange\Data\DebitData;
use LBHurtado\XChange\Data\IssuerData;
use LBHurtado\XChange\Data\PayCode\GeneratePayCodeResultData;
use LBHurtado\XChange\Data\PayCodeLinksData;
use LBHurtado\XChange\Data\PricingEstimateData;
use LBHurtado\XChange\Data\Redemption\SubmitPayCodeClaimResultData;
use LBHurtado\XChange\Data\Settlement\SettlementEnvelopeReadinessData;
use LBHurtado\XChange\Enums\PaymentAttemptStatus;
use LBHurtado\XChange\Enums\PaymentVerificationTrigger;
use LBHurtado\XChange\Exceptions\IncompleteClaimEvidence;
use LBHurtado\XChange\Exceptions\PayCodeIssuanceBusy;
use LBHurtado\XChange\Http\Middleware\ShareXChangeBranding;
use LBHurtado\XChange\Models\CampaignDisplaySession;
use LBHurtado\XChange\Models\LeadCampaign;
use LBHurtado\XChange\Models\PayCodeTemplate;
use LBHurtado\XChange\Models\PaymentAttempt;
use LBHurtado\XChange\Models\VoucherClaim;
use LBHurtado\XChange\Services\Funding\FundingProviderAdapterRegistry;
use LBHurtado\XChange\Tests\Fakes\FakeFundingProviderAdapter;

beforeEach(function (): void {
    $this->withoutMiddleware(ShareXChangeBranding::class);
    Http::preventStrayRequests();
    config()->set('x-change.payment.attempts.enabled', true);
    config()->set('x-change.payment.attempts.provider', 'netbank');
    config()->set('x-change.funding.providers.netbank.enabled', true);
    $this->displayAdapter = new FakeFundingProviderAdapter;
    app()->instance(FundingProviderAdapterRegistry::class, new FundingProviderAdapterRegistry([$this->displayAdapter]));
    $this->displayOwner = actingAsTestUser();
    $template = PayCodeTemplate::query()->create([
        'owner_type' => $this->displayOwner->getMorphClass(),
        'owner_id' => (string) $this->displayOwner->getKey(),
        'name' => 'Paired application',
        'base_template_key' => 'blank-pay-code',
        'instructions_ciphertext' => validVoucherInstructions(amount: 0, overrides: [
            'target_amount' => 105.13,
            'metadata' => [
                'flow_type' => 'settlement',
                'collection_wallet_id' => $this->displayOwner->wallet->id,
            ],
        ])->toArray(),
        'status' => 'active',
    ]);
    $this->displayCampaign = app(CreateLeadCampaign::class)->handle($this->displayOwner, $template, [
        'title' => 'Paired application',
        'endpoint_slug' => 'paired-application',
    ]);
    $this->generatedDisplayCodes = [];
    $this->busyDisplayAttempts = 0;
    $this->displayGenerationCalls = 0;
    $generator = Mockery::mock(GeneratePayCode::class);
    $generator->shouldReceive('handle')->andReturnUsing(function (array $input): GeneratePayCodeResultData {
        $this->displayGenerationCalls++;
        $voucher = issueVoucher(validVoucherInstructions(amount: 0, overrides: $input));
        if ($this->busyDisplayAttempts > 0) {
            $this->busyDisplayAttempts--;
            throw new PayCodeIssuanceBusy;
        }
        $this->generatedDisplayCodes[] = $voucher->code;

        return new GeneratePayCodeResultData(
            voucher_id: $voucher->getKey(),
            code: $voucher->code,
            amount: 0,
            currency: 'PHP',
            issuer: new IssuerData(id: $this->displayOwner->getKey()),
            cost: new PricingEstimateData(currency: 'PHP', total: 0),
            wallet: [],
            debit: new DebitData,
            links: new PayCodeLinksData(
                redeem: route('x-change.claim.show', $voucher->code),
                redeem_path: route('x-change.claim.show', $voucher->code, false),
            ),
        );
    });
    app()->instance(GeneratePayCode::class, $generator);
    $this->withSession(['x-change.payment.browser-key' => 'paired-customer-browser']);
});

it('creates a seller display without minting a voucher or charging a wallet', function (): void {
    $balance = $this->displayOwner->wallet->balance;
    $response = $this->postJson(route('x-change.cockpit.display-sessions.store'), [
        'campaign_reference' => $this->displayCampaign->reference,
    ])->assertOk()->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('session.status', 'ready')->assertJsonPath('session.pay_code', null);

    expect($response->json('session.entry_qr_data_uri'))->toStartWith('data:image/')
        ->and($response->json('session.public_url'))->toContain('display=')
        ->and(Voucher::query()->count())->toBe(0)
        ->and($this->generatedDisplayCodes)->toBeEmpty()
        ->and($this->displayCampaign->fresh()->usage_count)->toBe(0)
        ->and($this->displayOwner->wallet->fresh()->balance)->toBe($balance)
        ->and($this->displayAdapter->instructionCalls)->toBe(0);
});

it('launches the paired browser scenario in seller QR mode without issuing a code', function (): void {
    $response = $this->post(route('x-change.cockpit.campaigns.lead-scenario-runner.store'), [
        'scenario' => 'paired_campaign_qr',
    ]);
    $session = CampaignDisplaySession::query()->sole();
    $response->assertRedirect(route('x-change.cockpit.quick-generate', [
        'surface' => 'qr',
        'display_session' => $session->reference,
    ]));
    expect($session->voucher_id)->toBeNull()
        ->and($session->campaign->usage_count)->toBe(0)
        ->and(Voucher::query()->count())->toBe(0)
        ->and($this->generatedDisplayCodes)->toBeEmpty();
});

it('isolates seller create read end and reset operations by campaign owner', function (): void {
    $session = createPairedDisplay();
    actingAsTestUser();
    $this->postJson(route('x-change.cockpit.display-sessions.store'), [
        'campaign_reference' => $this->displayCampaign->reference,
    ])->assertNotFound();
    $this->getJson(route('x-change.cockpit.display-sessions.show', $session->reference))->assertNotFound();
    foreach (['end', 'reset'] as $action) {
        $this->postJson(route('x-change.cockpit.display-sessions.'.$action, $session->reference))->assertNotFound();
    }
    expect(CampaignDisplaySession::query()->count())->toBe(1)
        ->and($session->fresh()->ended_at)->toBeNull();
});

it('keeps seller write capacity available after repeated display polling', function (): void {
    $session = createPairedDisplay();
    for ($poll = 0; $poll < 13; $poll++) {
        $this->getJson(route('x-change.cockpit.display-sessions.show', $session->reference))
            ->assertOk()->assertHeader('X-RateLimit-Limit', '30');
    }
    $this->postJson(route('x-change.cockpit.display-sessions.end', $session->reference))
        ->assertOk()->assertHeader('X-RateLimit-Limit', '12')
        ->assertJsonPath('session.status', 'ended');
    expect($session->fresh()->ended_at)->not->toBeNull();
});

it('enforces the polling limit without exhausting the separate seller write limit', function (): void {
    $session = createPairedDisplay();
    for ($poll = 0; $poll < 30; $poll++) {
        $this->getJson(route('x-change.cockpit.display-sessions.show', $session->reference))->assertOk();
    }
    $this->getJson(route('x-change.cockpit.display-sessions.show', $session->reference))->assertTooManyRequests();
    $this->postJson(route('x-change.cockpit.display-sessions.reset', $session->reference))
        ->assertOk()->assertJsonPath('session.status', 'ready');
    expect(CampaignDisplaySession::query()->count())->toBe(2);
});

it('mints exactly once on the first scan and reopens the same code on retries', function (): void {
    $session = createPairedDisplay();
    $url = pairedDisplayEntryUrl($session);
    $this->get($url)->assertRedirect();
    $code = $session->fresh()->voucher->code;
    $this->get($url)->assertRedirect(route('x-change.claim.show', $code));
    expect($this->generatedDisplayCodes)->toBe([$code])
        ->and(Voucher::query()->count())->toBe(1)
        ->and($this->displayCampaign->fresh()->usage_count)->toBe(1);

    $this->withSession(['x-change.payment.browser-key' => 'other-browser'])->get($url)->assertConflict();
    expect(Voucher::query()->count())->toBe(1);
});

it('rejects tokens under the wrong campaign and malformed tokens', function (): void {
    $session = createPairedDisplay();
    $other = app(CreateLeadCampaign::class)->handle($this->displayOwner, $this->displayCampaign->payCodeTemplate, [
        'title' => 'Other endpoint', 'endpoint_slug' => 'other-endpoint',
    ]);
    $this->get(pairedDisplayEntryUrl($session, $other))->assertNotFound();
    $this->get(route('x-change.leads.start', [
        'merchant_slug' => $other->merchant_slug, 'endpoint_slug' => $other->endpoint_slug, 'display' => 'bad-token',
    ]))->assertNotFound();
    expect($this->generatedDisplayCodes)->toBeEmpty();
});

it('retries rolled back busy issuance and binds exactly one persisted voucher', function (): void {
    $session = createPairedDisplay();
    $this->busyDisplayAttempts = 1;
    $this->get(pairedDisplayEntryUrl($session))->assertRedirect();
    expect($this->displayGenerationCalls)->toBe(2)
        ->and(Voucher::query()->count())->toBe(1)
        ->and($this->generatedDisplayCodes)->toHaveCount(1)
        ->and($session->fresh()->voucher->code)->toBe($this->generatedDisplayCodes[0])
        ->and($this->displayCampaign->fresh()->usage_count)->toBe(1);
});

it('fails closed after exhausting bounded busy issuance retries', function (): void {
    $session = createPairedDisplay();
    $this->busyDisplayAttempts = 3;
    $balance = $this->displayOwner->wallet->balance;
    $this->withoutExceptionHandling();
    expect(fn () => $this->get(pairedDisplayEntryUrl($session)))->toThrow(PayCodeIssuanceBusy::class);
    expect($this->displayGenerationCalls)->toBe(3)
        ->and(Voucher::query()->count())->toBe(0)
        ->and($session->fresh()->voucher_id)->toBeNull()
        ->and($session->fresh()->browser_hash)->toBeNull()
        ->and($this->displayCampaign->fresh()->usage_count)->toBe(0)
        ->and($this->displayOwner->wallet->fresh()->balance)->toBe($balance);
});

it('rejects expired and ended display scans without issuance', function (string $terminal): void {
    $session = createPairedDisplay();
    $session->update([$terminal => now()->subMinute()]);
    $this->get(pairedDisplayEntryUrl($session))->assertGone();
    expect($this->generatedDisplayCodes)->toBeEmpty();
})->with(['expired' => 'expires_at', 'ended' => 'ended_at']);

it('resets to a fresh token while retaining the old code and rejecting duplicate resets', function (): void {
    $session = createPairedDisplay();
    $this->get(pairedDisplayEntryUrl($session))->assertRedirect();
    $voucher = $session->fresh()->voucher;
    $response = $this->postJson(route('x-change.cockpit.display-sessions.reset', $session->reference))
        ->assertOk()->assertJsonPath('session.status', 'ready')->assertJsonPath('session.pay_code', null);
    expect($response->json('session.reference'))->not->toBe($session->reference)
        ->and($response->json('session.public_url'))->not->toBe(pairedDisplayEntryUrl($session))
        ->and($session->fresh()->ended_at)->not->toBeNull()
        ->and($session->fresh()->voucher_id)->toBe($voucher->getKey());
    $this->assertModelExists($voucher);
    $this->get(pairedDisplayEntryUrl($session))->assertGone();
    $this->postJson(route('x-change.cockpit.display-sessions.reset', $session->reference))->assertConflict();
    expect(CampaignDisplaySession::query()->count())->toBe(2)
        ->and(Voucher::query()->count())->toBe(1);
});

it('requires completed intake before creating paired payment instructions', function (): void {
    $session = createPairedDisplay();
    $this->get(pairedDisplayEntryUrl($session))->assertRedirect();
    $voucher = $session->fresh()->voucher;
    $this->withHeader('X-Inertia', 'true')->get(route('x-change.pay.show', $voucher->code))
        ->assertOk()->assertJsonPath('props.payment.can_create_attempt', false);
    $this->postJson(route('x-change.pay.attempts.store', $voucher->code))->assertConflict();
    expect(PaymentAttempt::query()->count())->toBe(0)
        ->and($this->displayAdapter->instructionCalls)->toBe(0);
    $readiness = Mockery::mock(SettlementEnvelopeReadinessContract::class);
    $readiness->shouldReceive('check')->andReturn(SettlementEnvelopeReadinessData::notRequired());
    app()->instance(SettlementEnvelopeReadinessContract::class, $readiness);
    $result = app(SubmitWebPayCodeClaim::class)->handle($voucher, ['mobile' => '639171234567']);
    expect($result)->toBeInstanceOf(SubmitPayCodeClaimResultData::class)
        ->and($result->claim_type)->toBe('settlement')
        ->and($result->status)->toBe('pending')
        ->and($result->claimed)->toBeFalse()
        ->and($voucher->fresh()->redeemed_at)->toBeNull()
        ->and($session->fresh()->intake_completed_at)->not->toBeNull();
    $this->post(route('x-change.pay.attempts.store', $voucher->code))->assertRedirect();
    expect($session->fresh()->payment_attempt_id)->not->toBeNull()
        ->and($this->displayAdapter->instructionCalls)->toBe(1);
});

it('allows paired pure payable payment without intake while keeping the QR seller only', function (): void {
    $template = $this->displayCampaign->payCodeTemplate;
    $instructions = $template->instructions_ciphertext;
    data_set($instructions, 'metadata.flow_type', 'collectible');
    data_set($instructions, 'inputs.fields', []);
    $template->update(['instructions_ciphertext' => $instructions]);
    $session = createPairedDisplay();
    $this->get(pairedDisplayEntryUrl($session))->assertRedirect();
    $voucher = $session->fresh()->voucher;

    $this->withHeader('X-Inertia', 'true')->get(route('x-change.claim.show', $voucher->code))
        ->assertOk()->assertJsonPath('component', 'x-change/claim/PaymentHandoff');
    $this->get(route('x-change.pay.show', $voucher->code))->assertOk()
        ->assertJsonPath('props.payment.can_create_attempt', true);
    $this->post(route('x-change.pay.attempts.store', $voucher->code))->assertRedirect();
    $this->get(route('x-change.pay.show', $voucher->code))->assertOk()
        ->assertJsonPath('props.payment.attempt.status', 'awaiting_payment')
        ->assertJsonPath('props.payment.attempt.qr_code', null);
    $this->getJson(route('x-change.cockpit.display-sessions.show', $session->reference))->assertOk()
        ->assertJsonPath('session.status', 'awaiting_payment')
        ->assertJsonStructure(['session' => ['attempt' => ['qr_code' => ['base64_payload']]]]);
    expect($session->fresh()->intake_completed_at)->toBeNull()
        ->and($voucher->fresh()->redeemed_at)->toBeNull()
        ->and($this->displayAdapter->instructionCalls)->toBe(1)
        ->and(PaymentAttempt::query()->sole()->status)->toBe(PaymentAttemptStatus::AwaitingPayment);
});

it('shows provider QR only to the seller and polls without writes or provider verification', function (): void {
    $session = createPairedDisplay();
    $this->get(pairedDisplayEntryUrl($session))->assertRedirect();
    $voucher = $session->fresh()->voucher;
    $session->update(['intake_completed_at' => now()]);
    $this->post(route('x-change.pay.attempts.store', $voucher->code))->assertRedirect();
    $attempt = PaymentAttempt::query()->sole();
    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });
    for ($poll = 0; $poll < 2; $poll++) {
        $this->getJson(route('x-change.cockpit.display-sessions.show', $session->reference))->assertOk()
            ->assertJsonPath('session.status', 'awaiting_payment')
            ->assertJsonPath('session.entry_qr_data_uri', null)
            ->assertJsonPath('session.attempt.reference', $attempt->reference)
            ->assertJsonStructure(['session' => ['attempt' => ['qr_code' => ['base64_payload']]]]);
        $this->withHeader('X-Inertia', 'true')->get(route('x-change.pay.show', $voucher->code))->assertOk()
            ->assertJsonPath('props.payment.paired_display.reference', $session->reference)
            ->assertJsonPath('props.payment.attempt.qr_code', null)
            ->assertJsonPath('props.payment.attempt.status', 'awaiting_payment');
    }
    expect(array_filter($queries, fn (string $sql): bool => preg_match('/^\s*(insert|update|delete|replace)\b/i', $sql) === 1))->toBeEmpty()
        ->and($this->displayAdapter->instructionCalls)->toBe(1)
        ->and($this->displayAdapter->lastVerification)->toBeNull();
    app(TransitionPaymentAttempt::class)->handle(
        $attempt,
        PaymentAttemptStatus::Settled,
        'test_settlement_projection',
        PaymentVerificationTrigger::Operator,
    );
    $this->getJson(route('x-change.cockpit.display-sessions.show', $session->reference))->assertOk()
        ->assertJsonPath('session.status', 'paid')->assertJsonPath('session.attempt', null);
});

it('keeps verified payment completion visible after the paired QR expires', function (): void {
    $session = createPairedDisplay();
    $this->get(pairedDisplayEntryUrl($session))->assertRedirect();
    $voucher = $session->fresh()->voucher;
    $session->update(['intake_completed_at' => now()]);
    $this->post(route('x-change.pay.attempts.store', $voucher->code))->assertRedirect();
    $attempt = PaymentAttempt::query()->sole();
    app(TransitionPaymentAttempt::class)->handle($attempt, PaymentAttemptStatus::Verified, 'test_verification', PaymentVerificationTrigger::Operator);
    $this->travel(30)->minutes();
    $this->getJson(route('x-change.cockpit.display-sessions.show', $session->reference))->assertOk()
        ->assertJsonPath('session.status', 'completing')
        ->assertJsonPath('session.attempt', null)
        ->assertJsonPath('session.entry_qr_data_uri', null);
    expect($this->displayAdapter->lastVerification)->toBeNull();
});

it('does not unlock paired payment when required intake evidence is invalid or missing', function (mixed $name): void {
    $template = $this->displayCampaign->payCodeTemplate;
    $instructions = $template->instructions_ciphertext;
    data_set($instructions, 'inputs.fields', ['name']);
    $template->update(['instructions_ciphertext' => $instructions]);
    $session = createPairedDisplay();
    $this->get(pairedDisplayEntryUrl($session))->assertRedirect();
    $voucher = $session->fresh()->voucher;
    expect(fn () => app(SubmitWebPayCodeClaim::class)->handle($voucher, [
        'mobile' => '639171234567', 'inputs' => ['name' => $name],
    ]))->toThrow(IncompleteClaimEvidence::class);
    expect($session->fresh()->intake_completed_at)->toBeNull()
        ->and(VoucherClaim::query()->where('voucher_id', $voucher->id)->count())->toBe(0);
    $this->postJson(route('x-change.pay.attempts.store', $voucher->code))->assertConflict();
    expect($this->displayAdapter->instructionCalls)->toBe(0);
})->with(['missing' => null, 'empty' => '', 'blank' => '   ', 'empty array' => [[]]]);

it('records persisted valid intake independently of settlement execution status', function (string $status): void {
    $template = $this->displayCampaign->payCodeTemplate;
    $instructions = $template->instructions_ciphertext;
    data_set($instructions, 'inputs.fields', ['name']);
    $template->update(['instructions_ciphertext' => $instructions]);
    $session = createPairedDisplay();
    $this->get(pairedDisplayEntryUrl($session))->assertRedirect();
    $voucher = $session->fresh()->voucher;
    if ($status === 'pending') {
        $readiness = Mockery::mock(SettlementEnvelopeReadinessContract::class);
        $readiness->shouldReceive('check')->andReturn(SettlementEnvelopeReadinessData::notRequired());
        app()->instance(SettlementEnvelopeReadinessContract::class, $readiness);
    }
    $result = app(SubmitWebPayCodeClaim::class)->handle($voucher, [
        'mobile' => '639171234567', 'inputs' => ['name' => 'Test Applicant'],
    ]);
    expect($result->claim_type)->toBe('settlement')
        ->and($result->status)->toBe($status)
        ->and($result->claimed)->toBeFalse()
        ->and($voucher->fresh()->redeemed_at)->toBeNull()
        ->and($session->fresh()->intake_completed_at)->not->toBeNull()
        ->and(VoucherClaim::query()->where('voucher_id', $voucher->id)->sole()->evidence)->toHaveCount(1)
        ->and($this->displayAdapter->instructionCalls)->toBe(0)
        ->and(PaymentAttempt::query()->count())->toBe(0);
    $this->post(route('x-change.pay.attempts.store', $voucher->code))->assertRedirect();
    expect($this->displayAdapter->instructionCalls)->toBe(1)
        ->and(PaymentAttempt::query()->sole()->status)->toBe(PaymentAttemptStatus::AwaitingPayment);
})->with(['blocked', 'pending']);

it('blocks another browser from paired payment pages and creation', function (): void {
    $session = createPairedDisplay();
    $this->get(pairedDisplayEntryUrl($session))->assertRedirect();
    $voucher = $session->fresh()->voucher;
    $session->update(['intake_completed_at' => now()]);
    $this->withSession(['x-change.payment.browser-key' => 'intruder']);
    $this->get(route('x-change.pay.show', $voucher->code))->assertForbidden();
    $this->get(route('x-change.claim.show', $voucher->code))->assertForbidden();
    $this->postJson(route('x-change.pay.attempts.store', $voucher->code))->assertForbidden();
    expect($this->displayAdapter->instructionCalls)->toBe(0);
});

it('marks paired success for payment and protects it from other browsers', function (): void {
    $session = createPairedDisplay();
    $this->get(pairedDisplayEntryUrl($session))->assertRedirect();
    $voucher = $session->fresh()->voucher;
    $session->update(['intake_completed_at' => now()]);

    $this->getJson(route('x-change.claim.success', $voucher->code))->assertOk()
        ->assertJsonPath('paired_payment', true)
        ->assertJsonPath('success_action.target.url', route('x-change.pay.show', $voucher->code));
    $this->withSession(['x-change.payment.browser-key' => 'other-success-browser'])
        ->getJson(route('x-change.claim.success', $voucher->code))->assertForbidden();
    expect($this->displayAdapter->instructionCalls)->toBe(0);
});

it('shows redeemed noncollectible displays as completed without a payment handoff', function (): void {
    $template = $this->displayCampaign->payCodeTemplate;
    $template->update(['instructions_ciphertext' => validVoucherInstructions(amount: 0, overrides: [
        'metadata' => ['flow_type' => 'disbursable'],
    ])->toArray()]);
    $session = createPairedDisplay();
    $this->get(pairedDisplayEntryUrl($session))->assertRedirect();
    $voucher = $session->fresh()->voucher;
    $voucher->forceFill(['redeemed_at' => now()])->save();

    $this->getJson(route('x-change.cockpit.display-sessions.show', $session->reference))->assertOk()
        ->assertJsonPath('session.status', 'completed')
        ->assertJsonPath('session.attempt', null)
        ->assertJsonPath('session.entry_qr_data_uri', null);
    $this->getJson(route('x-change.claim.success', $voucher->code))->assertOk()
        ->assertJsonPath('paired_payment', false)->assertJsonPath('success_action', null);
    $session->update(['expires_at' => now()->subMinute()]);
    $this->getJson(route('x-change.cockpit.display-sessions.show', $session->reference))->assertOk()
        ->assertJsonPath('session.status', 'completed');
    expect($this->displayAdapter->instructionCalls)->toBe(0);
});

it('rejects payment creation after the paired display expires or ends', function (string $terminal): void {
    $session = createPairedDisplay();
    $this->get(pairedDisplayEntryUrl($session))->assertRedirect();
    $voucher = $session->fresh()->voucher;
    $session->update(['intake_completed_at' => now()]);
    $session->update([$terminal => now()->subMinute()]);
    $this->postJson(route('x-change.pay.attempts.store', $voucher->code))->assertGone();
    expect($this->displayAdapter->instructionCalls)->toBe(0);
})->with(['expired' => 'expires_at', 'ended' => 'ended_at']);

it('keeps unpaired endpoint and payer QR behavior unchanged', function (): void {
    $url = route('x-change.leads.start', [
        'merchant_slug' => $this->displayCampaign->merchant_slug,
        'endpoint_slug' => $this->displayCampaign->endpoint_slug,
    ]);
    $startUrl = route('x-change.leads.start.submit', [
        'merchant_slug' => $this->displayCampaign->merchant_slug,
        'endpoint_slug' => $this->displayCampaign->endpoint_slug,
    ]);
    $this->withHeader('X-Inertia', 'true')->get($url)->assertOk();
    $this->post($startUrl)->assertRedirect();
    $this->post($startUrl)->assertRedirect();
    expect(Voucher::query()->count())->toBe(1)
        ->and(CampaignDisplaySession::query()->count())->toBe(0);
    $voucher = Voucher::query()->firstOrFail();
    $this->post(route('x-change.pay.attempts.store', $voucher->code))->assertRedirect();
    $attempt = PaymentAttempt::query()->sole();
    $this->withHeader('X-Inertia', 'true')->get(route('x-change.pay.show', [
        'code' => $voucher->code, 'attempt' => $attempt->reference,
    ]))->assertOk()->assertJsonPath('props.payment.paired_display', null)
        ->assertJsonStructure(['props' => ['payment' => ['attempt' => ['qr_code' => ['base64_payload']]]]]);
});

function createPairedDisplay(): CampaignDisplaySession
{
    test()->postJson(route('x-change.cockpit.display-sessions.store'), [
        'campaign_reference' => test()->displayCampaign->reference,
    ])->assertOk();

    return CampaignDisplaySession::query()->latest('id')->firstOrFail();
}

function pairedDisplayEntryUrl(CampaignDisplaySession $session, ?LeadCampaign $campaign = null): string
{
    $campaign ??= $session->campaign;

    return route('x-change.leads.start', [
        'merchant_slug' => $campaign->merchant_slug,
        'endpoint_slug' => $campaign->endpoint_slug,
        'display' => $session->entry_token,
    ]);
}
