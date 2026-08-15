<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Auth;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\PartnerApi\CreatePartnerApiClient;
use LBHurtado\XChange\Contracts\VoucherLifecycleServiceContract;
use LBHurtado\XChange\Tests\Fakes\User;

function authenticatePartnerPayCodeOwner(User $issuer, array $scopes): void
{
    $credential = app(CreatePartnerApiClient::class)->handle(
        name: 'Saras AI Sandbox',
        issuer: $issuer,
        scopes: $scopes,
    );

    Passport::actingAsClient(Client::query()->findOrFail($credential->client_id), $scopes);
}

function partnerOwnedVoucher(User $issuer, string $code): Voucher
{
    $voucher = new Voucher([
        'code' => $code,
        'metadata' => [
            'instructions' => [
                'cash' => ['amount' => 125.50, 'currency' => 'PHP'],
                'inputs' => ['fields' => ['name']],
                'feedback' => [],
                'rider' => [],
            ],
        ],
        'state' => 'active',
    ]);
    $voucher->owner()->associate($issuer);
    $voucher->save();

    return $voucher;
}

function partnerLifecycleDetail(string $code, int|string $issuerId): array
{
    return [
        'code' => $code,
        'amount' => 125.50,
        'currency' => 'PHP',
        'issuer_id' => $issuerId,
        'operational_status' => [
            'key' => 'active',
            'label' => 'Active',
            'can_claim' => true,
            'is_terminal' => false,
        ],
        'capability' => [
            'key' => 'disbursement',
            'label' => 'Disbursement',
            'voucher_type_label' => 'Redeemable',
        ],
        'party' => ['target' => ['label' => 'Mobile-bound', 'masked' => '•••• 4567']],
        'created_at' => '2026-08-15T10:00:00+08:00',
        'starts_at' => null,
        'expires_at' => '2026-08-22T10:00:00+08:00',
        'redeemed_at' => null,
        'claimed' => false,
        'fully_claimed' => false,
        'attention' => null,
        'instructions' => ['cash' => ['validation' => ['secret' => 'never-expose-this']]],
        'claim_evidence' => [['name' => 'Private Person']],
        'settlement_envelope' => ['private' => true],
    ];
}

it('returns a minor-unit sanitized Pay Code status only to its token-bound owner', function () {
    $issuer = actingAsTestUser();
    authenticatePartnerPayCodeOwner($issuer, ['pay-codes:read']);
    $voucher = partnerOwnedVoucher($issuer, 'SARS-READ');
    $lifecycle = Mockery::mock(VoucherLifecycleServiceContract::class);
    $lifecycle->shouldReceive('show')->once()->with((string) $voucher->getKey())
        ->andReturn(partnerLifecycleDetail($voucher->code, $issuer->getKey()));
    $this->app->instance(VoucherLifecycleServiceContract::class, $lifecycle);

    $this->getJson('/api/partner/v1/pay-codes/SARS-READ')
        ->assertSuccessful()
        ->assertJsonPath('data.schema', 'x-change.partner-pay-code.v1')
        ->assertJsonPath('data.amount_minor', 12550)
        ->assertJsonPath('data.currency', 'PHP')
        ->assertJsonPath('data.status.key', 'active')
        ->assertJsonMissingPath('data.instructions')
        ->assertJsonMissingPath('data.claim_evidence')
        ->assertJsonMissingPath('data.settlement_envelope')
        ->assertJsonMissingPath('data.issuer_id');
});

it('conceals another issuer Pay Code behind the same not-found response', function () {
    $issuer = actingAsTestUser();
    $other = User::query()->create([
        'name' => 'Other Issuer',
        'email' => 'other-partner@example.test',
        'password' => 'password',
    ]);
    authenticatePartnerPayCodeOwner($issuer, ['pay-codes:read']);
    partnerOwnedVoucher($other, 'SARS-OTHER');

    $this->getJson('/api/partner/v1/pay-codes/SARS-OTHER')->assertNotFound();
});

it('cancels only an owned Pay Code through the Treasury-safe lifecycle service', function () {
    $issuer = actingAsTestUser();
    authenticatePartnerPayCodeOwner($issuer, ['pay-codes:cancel']);
    $voucher = partnerOwnedVoucher($issuer, 'SARS-CANCEL');
    $lifecycle = Mockery::mock(VoucherLifecycleServiceContract::class);
    $lifecycle->shouldReceive('show')->once()->with((string) $voucher->getKey())
        ->andReturn(partnerLifecycleDetail($voucher->code, $issuer->getKey()));
    $lifecycle->shouldReceive('cancel')->once()->with('SARS-CANCEL', [
        'reason' => 'Recipient request withdrawn.',
    ])->andReturnUsing(function (): array {
        expect(Auth::id())->not->toBeNull();

        return [
            'code' => 'SARS-CANCEL',
            'status' => 'cancelled',
            'cancelled' => true,
            'reason' => 'Recipient request withdrawn.',
            'treasury_release' => [
                'released' => true,
                'replayed' => false,
                'amount_minor' => 12550,
                'currency' => 'PHP',
                'operation_reference' => 'private-operation-reference',
            ],
        ];
    });
    $this->app->instance(VoucherLifecycleServiceContract::class, $lifecycle);

    $this->postJson('/api/partner/v1/pay-codes/SARS-CANCEL/cancellation', [
        'reason' => 'Recipient request withdrawn.',
    ])->assertSuccessful()
        ->assertJsonPath('data.cancelled', true)
        ->assertJsonPath('data.treasury_release.amount_minor', 12550)
        ->assertJsonMissingPath('data.treasury_release.operation_reference');
});

it('requires cancellation scope and never invokes lifecycle for another owner', function () {
    $issuer = actingAsTestUser();
    $other = User::query()->create([
        'name' => 'Other Issuer',
        'email' => 'other-cancel@example.test',
        'password' => 'password',
    ]);
    authenticatePartnerPayCodeOwner($issuer, ['pay-codes:read']);
    partnerOwnedVoucher($other, 'SARS-DENY');
    $lifecycle = Mockery::mock(VoucherLifecycleServiceContract::class);
    $lifecycle->shouldNotReceive('cancel');
    $this->app->instance(VoucherLifecycleServiceContract::class, $lifecycle);

    $this->postJson('/api/partner/v1/pay-codes/SARS-DENY/cancellation', [
        'reason' => 'Unauthorized attempt.',
    ])->assertForbidden();
});
