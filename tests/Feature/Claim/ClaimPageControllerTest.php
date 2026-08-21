<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Voucher\Services\VoucherSlicePlanFactory;
use LBHurtado\XCampaign\Contracts\CampaignWorksheetRepository;
use LBHurtado\XCampaign\Data\CampaignWorksheetData;
use LBHurtado\XCampaign\Data\CampaignWorksheetRowData;
use LBHurtado\XChange\Actions\Campaigns\IssueCampaignWorksheetApprovalPayCode;
use LBHurtado\XChange\Models\VoucherClaim;
use LBHurtado\XChange\Services\Slices\VoucherSliceExecutionCoordinator;
use LBHurtado\XChange\Support\Claim\ClaimAuthenticationIntent;
use LBHurtado\XChange\Tests\Fakes\User;

it('renders the canonical human claim page without exposing the experience JSON', function () {
    $voucher = issueVoucher(validVoucherInstructions(100));

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.claim.show', ['code' => strtolower((string) $voucher->code)]))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/claim/Entry')
        ->assertJsonPath('props.initial_code', (string) $voucher->code)
        ->assertJsonPath('props.provisioning_requirement', null)
        ->assertJsonStructure(['props' => ['claim_experience']]);
});

it('hydrates the initial claim page with sanitized canonical slice X-Ray rows', function () {
    config()->set('x-ray.disclosure.guest.show_remaining_slices', 'if_allowed_by_voucher');

    $plan = app(VoucherSlicePlanFactory::class)->equal(15_000, 'PHP', 4);
    $voucher = issueVoucher(validVoucherInstructions(
        amount: 150,
        overrides: ['slice_plan' => $plan->canonicalArray()],
    ));
    auth()->logout();

    $response = $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.claim.show', ['code' => $voucher->code]))
        ->assertOk()
        ->assertJsonPath('props.claim_surface.visibility', 'public_preview');

    $components = collect($response->json('props.claim_surface.components'));
    $xray = $components->firstWhere('type', 'xray_preview');
    $slices = collect($xray['props']['disclosures'])
        ->firstWhere('key', 'remaining_slices')['value'];

    expect($slices)->toHaveCount(4)
        ->and(collect($slices)->pluck('label')->all())
        ->toBe(['Slice 1', 'Slice 2', 'Slice 3', 'Slice 4']);
});

it('hydrates a partially claimed flexible plan with prior activity and remaining capacity', function () {
    config()->set('x-ray.disclosure.guest.show_remaining_slices', 'if_allowed_by_voucher');

    $plan = app(VoucherSlicePlanFactory::class)->flexible(
        totalMinor: 30_000,
        currency: 'PHP',
        maxSlices: 3,
        minAmountMinor: 5_000,
    );
    $voucher = issueVoucher(validVoucherInstructions(
        amount: 300,
        overrides: ['slice_plan' => $plan->canonicalArray()],
    ));
    $coordinator = app(VoucherSliceExecutionCoordinator::class);
    $reservation = $coordinator->reserve($voucher, [
        'amount' => '50.00',
        '_meta' => ['idempotency_key' => 'partial-flexible-claim'],
    ]);
    $claim = VoucherClaim::query()->create([
        'voucher_id' => $voucher->getKey(),
        'claim_number' => $reservation->execution->claim_number,
        'claim_type' => 'withdraw',
        'status' => 'succeeded',
        'requested_amount_minor' => 5_000,
        'disbursed_amount_minor' => 5_000,
        'remaining_balance_minor' => 25_000,
        'currency' => 'PHP',
        'completed_at' => now(),
    ]);

    $coordinator->begin($reservation->execution);
    $coordinator->succeed($reservation->execution, $claim);
    auth()->logout();

    $response = $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.claim.show', ['code' => $voucher->code]))
        ->assertOk()
        ->assertJsonPath('props.claim_surface.state.key', 'partially_claimed')
        ->assertJsonPath('props.claim_surface.state.can_claim', true);

    $xray = collect($response->json('props.claim_surface.components'))
        ->firstWhere('type', 'xray_preview');
    $slices = collect($xray['props']['disclosures'])
        ->firstWhere('key', 'remaining_slices')['value'];

    expect($slices)->toHaveCount(2)
        ->and(data_get($slices, '0.label'))->toBe('Claim 1')
        ->and(data_get($slices, '0.amount_minor'))->toBe(5_000)
        ->and(data_get($slices, '0.status_label'))->toBe('Paid')
        ->and(data_get($slices, '0.claimed_at'))->toBeString()
        ->and(data_get($slices, '1.label'))->toBe('Remaining capacity')
        ->and(data_get($slices, '1.amount_minor'))->toBe(25_000)
        ->and(data_get($slices, '1.max_slices'))->toBe(3)
        ->and(data_get($slices, '1.min_amount_minor'))->toBe(5_000)
        ->and(data_get($slices, '1.claims_used'))->toBe(1)
        ->and(data_get($slices, '1.claims_remaining'))->toBe(2)
        ->and(data_get($slices, '1.is_final_claim'))->toBeFalse();
});

it('renders the public claim error page for a missing code', function () {
    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.claim.show', ['code' => 'missing']))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/claim/Error')
        ->assertJsonPath('props.message', 'Invalid Pay Code.')
        ->assertJsonPath('props.code', 'MISSING');
});

it('does not admit a collectible Pay Code into the outward claim experience', function () {
    $voucher = issueVoucher(validVoucherInstructions(100, 'INSTAPAY', [
        'voucher_type' => 'payable',
        'target_amount' => 100,
        'metadata' => [
            'flow_type' => 'collectible',
        ],
    ]));

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.claim.show', ['code' => $voucher->code]))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/claim/Error')
        ->assertJsonPath('props.message', 'This Pay Code accepts payment and cannot be claimed.')
        ->assertJsonPath('props.code', (string) $voucher->code);
});

it('admits legacy campaign fulfillment Pay Codes into the outward claim experience', function () {
    $voucher = issueVoucher(validVoucherInstructions(100, 'INSTAPAY', [
        'metadata' => [
            'flow_type' => 'campaign_fulfillment',
            'source' => 'campaign',
        ],
    ]));

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.claim.show', ['code' => $voucher->code]))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/claim/Entry')
        ->assertJsonPath('props.initial_code', (string) $voucher->code);
});

it('routes unauthenticated campaign officer authorization to an explicit login handoff', function () {
    $issuer = campaignAuthorizationClaimPageUser();
    $repository = app(CampaignWorksheetRepository::class);

    $worksheet = $repository->put(new CampaignWorksheetData(
        reference: 'campaign-auth-claim-page-'.Str::lower(Str::random(8)),
        ownerType: $issuer->getMorphClass(),
        ownerId: (string) $issuer->getKey(),
        profile: 'payroll',
        name: 'Campaign Auth Claim Page',
        rows: [new CampaignWorksheetRowData(null, 1, ['mobile' => '09173011987'], 12_500)],
    ));

    $repository->freeze((string) $worksheet->reference, $issuer->getMorphClass(), (string) $issuer->getKey());

    $this->actingAs($issuer);
    $authorization = app(IssueCampaignWorksheetApprovalPayCode::class)->handle((string) $worksheet->reference, $issuer);
    auth()->logout();

    $voucher = Voucher::query()->where('code', $authorization->approval_pay_code)->sole();

    $this->get(route('x-change.claim.show', ['code' => $voucher->code]))
        ->assertRedirect(route('x-change.claim.authorization-required', ['code' => $voucher->code]))
        ->assertSessionHas('url.intended', route('x-change.claim.show', ['code' => $voucher->code]))
        ->assertSessionHas(ClaimAuthenticationIntent::SessionKey, function (array $payload) use ($voucher): bool {
            return $payload['type'] === 'campaign_authorization'
                && $payload['authentication_mode'] === 'authenticated_officer'
                && $payload['code'] === $voucher->code
                && $payload['workflow_key'] === 'campaign.officer-authorization.v1';
        });

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.claim.authorization-required', ['code' => $voucher->code]))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/claim/AuthRequired')
        ->assertJsonPath('props.code', (string) $voucher->code)
        ->assertJsonPath('props.intent.type', 'campaign_authorization')
        ->assertJsonPath('props.workflow.key', 'campaign.officer-authorization.v1');
});

it('requires the Account Funding claimant to sign in before starting the claim', function () {
    $system = actingAsTestUser();
    config()->set('account.system_user.candidates', [
        'x-change' => [
            'model' => User::class,
            'identifier' => $system->email,
            'identifier_column' => 'email',
        ],
    ]);
    $voucher = issueVoucher(validVoucherInstructions(500, 'INSTAPAY', [
        'claim' => [
            'outcomes' => [[
                'key' => 'account_funding',
                'pricing_profile' => 'account-funding-v1',
            ]],
            'selection' => 'server',
            'consumption' => 'one_of',
            'default_outcome' => 'account_funding',
            'onboarding' => ['mode' => 'if_required'],
            'claimant' => ['mode' => 'unbound'],
        ],
    ]));
    auth()->logout();

    $this->get(route('x-change.claim.show', ['code' => $voucher->code]))
        ->assertRedirect(route('x-change.claim.authorization-required', ['code' => $voucher->code]))
        ->assertSessionHas('url.intended', route('x-change.claim.show', ['code' => $voucher->code]))
        ->assertSessionHas(ClaimAuthenticationIntent::SessionKey, function (array $payload) use ($voucher): bool {
            return $payload['authentication_mode'] === 'claimant_handoff'
                && $payload['code'] === $voucher->code
                && $payload['workflow_key'] === 'account-funding.v1'
                && $payload['title'] === 'Account sign-in required';
        });

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.claim.authorization-required', ['code' => $voucher->code]))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/claim/AuthRequired')
        ->assertJsonPath('props.intent.title', 'Account sign-in required')
        ->assertJsonPath('props.workflow.key', 'account-funding.v1');
});

it('lets the issuer see the issuer console for their own already-claimed Pay Code', function () {
    $issuer = actingAsTestUser();
    $voucher = issueVoucher(validVoucherInstructions(100));
    VoucherClaim::query()->create([
        'voucher_id' => $voucher->getKey(),
        'claim_number' => 1,
        'claim_type' => 'withdraw',
        'status' => 'succeeded',
        'requested_amount_minor' => 10_000,
        'disbursed_amount_minor' => 10_000,
        'remaining_balance_minor' => 0,
        'currency' => 'PHP',
        'completed_at' => now(),
    ]);
    $voucher->forceFill(['redeemed_at' => now()])->save();

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.claim.show', ['code' => $voucher->code]))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/claim/Entry')
        ->assertJsonPath('props.claim_surface.visibility', 'issuer_console')
        ->assertJsonPath('props.claim_surface.viewer.role', 'issuer');
});

it('shows a guest the calm outcome panel instead of a hard error for an already-claimed Pay Code', function () {
    $issuer = actingAsTestUser();
    $voucher = issueVoucher(validVoucherInstructions(100));
    $voucher->forceFill(['redeemed_at' => now()])->save();
    auth()->logout();

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.claim.show', ['code' => $voucher->code]))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/claim/Entry')
        ->assertJsonPath('props.claim_surface.visibility', 'public_preview')
        ->assertJsonPath('props.claim_surface.viewer.role', 'guest')
        ->assertJsonPath('props.claim_surface.state.key', 'redeemed');
});

function campaignAuthorizationClaimPageUser(): User
{
    return User::query()->create([
        'name' => 'Campaign Authorization Claim Page User',
        'email' => 'campaign-auth-claim-page-'.Str::uuid().'@example.test',
        'password' => Hash::make('password'),
    ]);
}
