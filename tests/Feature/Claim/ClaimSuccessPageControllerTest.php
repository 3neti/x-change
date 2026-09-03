<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use LBHurtado\XChange\Http\Controllers\Web\Claim\ClaimSuccessPageController;
use LBHurtado\XChange\Support\Claim\ClaimExperiencePayload;
use LBHurtado\XChange\Support\Claim\CompiledClaimResultSession;
use LBHurtado\XRider\Contracts\RiderExperienceResolverContract;
use LBHurtado\XRider\Data\RiderExperienceData;
use LBHurtado\XRider\Data\RiderStageCollectionData;
use LBHurtado\XRider\Data\RiderSubjectData;
use LBHurtado\XRider\Enums\RiderOutcomeState;

beforeEach(function () {
    $viewsPath = __DIR__.'/../../Fixtures/views';

    if (! is_dir($viewsPath)) {
        mkdir($viewsPath, 0777, true);
    }

    file_put_contents($viewsPath.'/app.blade.php', <<<'BLADE'
<div id="app" data-page="{{ json_encode($page) }}"></div>
BLADE);

    app('view')->addLocation($viewsPath);

    config()->set('inertia.testing.ensure_pages_exist', false);

    app()->instance(
        RiderExperienceResolverContract::class,
        new class implements RiderExperienceResolverContract
        {
            public function resolve(RiderSubjectData $subject, array $context = []): RiderExperienceData
            {
                return new RiderExperienceData(
                    state: RiderOutcomeState::AcceptedSuccess,
                    subject: $subject,
                    stages: new RiderStageCollectionData(
                        stages: [],
                    ),
                );
            }
        }
    );

    Route::get('/x/claim/{code}/success', ClaimSuccessPageController::class)
        ->name('x-change.claim.success');
    Route::get('/x/cockpit', fn () => response('cockpit'))
        ->name('x-change.cockpit.entry');
    Route::get('/x/cockpit/overview', fn () => response('overview'))
        ->name('x-change.cockpit.dashboard');
    Route::get('/x/cockpit/quick-generate', fn () => response('quick-generate'))
        ->name('x-change.cockpit.quick-generate');
});

it('exposes claim experience redirect countdown metadata to the success page', function () {
    $this->withoutMiddleware();

    $voucher = issueVoucher(validVoucherInstructions(
        overrides: [
            'rider' => [
                'message' => 'SUCCESS DEMO: Thank you for claiming.',
                'url' => 'https://example.com/after-claim',
            ],
        ],
    ));

    $response = $this->getJson(route('x-change.claim.success', [
        'code' => $voucher->code,
    ], false).'?'.http_build_query([
        'state' => [
            'status' => 'completed',
        ],
        'subject' => [
            'type' => 'voucher',
            'id' => $voucher->getKey(),
            'code' => $voucher->code,
        ],
    ]))
        ->assertOk()
        ->assertJsonPath('claim_experience.options.show_redirect_countdown', true)
        ->assertJsonPath('claim_experience.diagnostics.redirect_owner', 'claim-widget')
        ->assertJsonPath('redirect.show_countdown', true)
        ->assertJsonPath('redirect.owner', 'claim-widget')
        ->assertJsonPath('redirect.delay_seconds', 5);

    $claimExperience = $response->json('claim_experience');

    expect(ClaimExperiencePayload::isClaimWidgetRedirect($claimExperience))->toBeTrue();
});

it('passes claim widget redirect ownership metadata to success page', function () {
    $voucher = issueVoucher(validVoucherInstructions(
        overrides: [
            'rider' => [
                'message' => 'Thank you for claiming.',
                'url' => 'https://example.com/success',
            ],
        ],
    ));

    $this
        ->getJson(route('x-change.claim.success', [
            'code' => $voucher->code,
        ]))
        ->assertOk()
        ->assertJsonPath('redirect.owner', 'claim-widget')
        ->assertJsonPath('redirect.show_countdown', true)
        ->assertJsonPath('redirect.delay_seconds', 5)
        ->assertJsonPath('redirectEndpoint', route('x-change.claim.redirect', [
            'code' => $voucher->code,
        ]));
});

it('does not enable success countdown when redirect owner is not claim widget', function () {
    $voucher = issueVoucher(validVoucherInstructions(
        overrides: [
            'rider' => [
                'message' => 'Thank you for claiming.',
            ],
        ],
    ));

    $this
        ->getJson(route('x-change.claim.success', [
            'code' => $voucher->code,
        ]))
        ->assertOk()
        ->assertJsonPath('redirect.owner', null)
        ->assertJsonPath('redirect.show_countdown', false)
        ->assertJsonPath('redirect.delay_seconds', null);
});

it('passes claim experience to success page payload', function () {
    $this->withoutMiddleware();

    $voucher = issueVoucher();

    $this->getJson(route('x-change.claim.success', [
        'code' => $voucher->code,
    ]))->assertOk()
        ->assertJsonStructure([
            'voucher' => [
                'code',
                'amount',
                'currency',
            ],
            'claimOutcome',
            'rider',
            'redirectEndpoint',
            'claim_experience',
            'redirect',
        ]);
});

it('does not apply a default Rider after an onboarding claim without authored Rider content', function (): void {
    $resolver = Mockery::mock(RiderExperienceResolverContract::class);
    $resolver->shouldNotReceive('resolve');
    $this->app->instance(RiderExperienceResolverContract::class, $resolver);

    $voucher = issueVoucher(validVoucherInstructions(
        overrides: [
            'onboarding' => true,
            'execution' => [
                'driver' => 'onboarding_account_provisioning',
            ],
            'rider' => [
                'message' => null,
                'url' => null,
                'splash' => null,
            ],
        ],
    ));

    $this->getJson(route('x-change.claim.success', [
        'code' => $voucher->code,
    ]))
        ->assertOk()
        ->assertJsonPath('rider', null)
        ->assertJsonPath('success_presentation.intent', 'commissioning_invitation')
        ->assertJsonPath('success_presentation.source', 'x-ray')
        ->assertJsonPath('success_presentation.eyebrow', 'Welcome')
        ->assertJsonPath('success_presentation.title', 'Welcome to Laravel')
        ->assertJsonPath('success_presentation.account_message', 'Your account is ready.')
        ->assertJsonPath('success_presentation.receipt_label', 'Invitation accepted')
        ->assertJsonPath('success_action.key', 'x-change.onboarding-success.enter-workspace')
        ->assertJsonPath('success_action.label', 'Continue')
        ->assertJsonPath('success_action.intent', 'enter_workspace')
        ->assertJsonPath('success_action.target.url', route('x-change.cockpit.entry'));
});

it('uses the commissioning role in onboarding success presentation and action routing', function (): void {
    config()->set('app.name', 'x-PayOut');

    $voucher = issueVoucher(validVoucherInstructions(
        overrides: [
            'onboarding' => true,
            'cash' => [
                'amount' => 1000,
                'currency' => 'PHP',
            ],
            'execution' => [
                'driver' => 'onboarding_account_provisioning',
            ],
            'metadata' => [
                'custom' => [
                    'x_payout_commissioning' => [
                        'role' => 'maker',
                    ],
                ],
            ],
            'rider' => [
                'message' => 'x-PayOut Maker onboarding invitation',
                'url' => null,
                'splash' => null,
            ],
        ],
    ));

    $this->getJson(route('x-change.claim.success', [
        'code' => $voucher->code,
    ]))
        ->assertOk()
        ->assertJsonPath('success_presentation.title', 'Welcome to x-PayOut')
        ->assertJsonPath('success_presentation.account_message', 'Your Maker account is ready.')
        ->assertJsonPath('success_presentation.funds.label', 'Client Funds')
        ->assertJsonPath('success_presentation.funds.text', '₱1,000.00 available for instructions')
        ->assertJsonPath('success_action.label', 'Go to my workspace')
        ->assertJsonPath('success_action.target.url', route('x-change.cockpit.quick-generate'));
});

it('passes compiled claim result to success page payload when present in session', function () {
    $voucher = issueVoucher();

    session()->put(CompiledClaimResultSession::KEY, [
        'status' => 'success',
        'claim_type' => 'withdraw',
        'voucher_code' => $voucher->code,
        'claimed' => true,
        'requested_amount' => null,
        'disbursed_amount' => null,
        'currency' => null,
        'remaining_balance' => null,
        'fully_claimed' => true,
        'messages' => ['Claim successful.'],
    ]);

    $this
        ->getJson(route('x-change.claim.success', [
            'code' => $voucher->code,
        ]))
        ->assertOk()
        ->assertJsonPath('compiled_claim_result.status', 'success')
        ->assertJsonPath('compiled_claim_result.claim_type', 'withdraw')
        ->assertJsonPath('compiled_claim_result.voucher_code', $voucher->code)
        ->assertJsonPath('compiled_claim_result.claimed', true)
        ->assertJsonPath('compiled_claim_result.fully_claimed', true)
        ->assertJsonPath('compiled_claim_result.messages.0', 'Claim successful.');
});

it('pulls compiled claim result after success page payload is rendered', function () {
    $voucher = issueVoucher();

    session()->put(CompiledClaimResultSession::KEY, [
        'status' => 'success',
        'voucher_code' => $voucher->code,
        'messages' => [],
    ]);

    $this
        ->getJson(route('x-change.claim.success', [
            'code' => $voucher->code,
        ]))
        ->assertOk()
        ->assertJsonPath('compiled_claim_result.status', 'success');

    expect(session()->has(CompiledClaimResultSession::KEY))->toBeFalse();

    $this
        ->getJson(route('x-change.claim.success', [
            'code' => $voucher->code,
        ]))
        ->assertOk()
        ->assertJsonPath('compiled_claim_result', null);
});
