<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XCampaign\Contracts\CampaignWorksheetRepository;
use LBHurtado\XCampaign\Data\CampaignWorksheetData;
use LBHurtado\XCampaign\Data\CampaignWorksheetRowData;
use LBHurtado\XChange\Actions\Campaigns\IssueCampaignWorksheetApprovalPayCode;
use LBHurtado\XChange\Support\Claim\CampaignOfficerAuthorizationLoginIntent;
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
        ->assertSessionHas(CampaignOfficerAuthorizationLoginIntent::SessionKey, function (array $payload) use ($voucher): bool {
            return $payload['type'] === 'campaign_authorization'
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

function campaignAuthorizationClaimPageUser(): User
{
    return User::query()->create([
        'name' => 'Campaign Authorization Claim Page User',
        'email' => 'campaign-auth-claim-page-'.Str::uuid().'@example.test',
        'password' => Hash::make('password'),
    ]);
}
