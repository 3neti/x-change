<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\FormHandlerOtp\Contracts\OtpChallengeGateway;
use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Data\ExecutionInstructionData;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Voucher\Services\ExecutionEngine;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryAllocationReadModelContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionOperationContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationReadModelQueryData;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionReservationData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryAllocationOperationType;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\Wallet\Treasury\Models\TreasuryAllocationOperation;
use LBHurtado\Wallet\Treasury\Models\TreasuryInventory;
use LBHurtado\XChange\Actions\PartnerApi\CreatePartnerApiClient;
use LBHurtado\XChange\Contracts\TreasuryAccountPortfolioProvisioningContract;
use LBHurtado\XChange\Contracts\VerifiedTreasuryFundingAllocationContract;
use LBHurtado\XChange\Models\PartnerApiOperation;
use LBHurtado\XChange\Models\StoredValueHolderBinding;
use LBHurtado\XChange\Tests\Fakes\FakeOtpChallengeGateway;
use LBHurtado\XChange\Tests\Fakes\User;

it('moves one OTP-authorized Partner API fare through the real wallet allocation exactly once', function (): void {
    $issuer = storedValueAcceptanceUser('Stored Value Issuer');
    $holder = storedValueAcceptanceUser('Stored Value Holder', '09173011987');
    $merchant = storedValueAcceptanceUser('Transit Merchant');
    enableNetbankTreasuryForTests();
    $portfolios = app(TreasuryAccountPortfolioProvisioningContract::class);
    $issuerPortfolio = $portfolios->provision($issuer, ['netbank-primary']);
    $portfolios->provision($holder, ['netbank-primary']);
    $portfolios->provision($merchant, ['netbank-primary']);
    $issuerClientFunds = collect($issuerPortfolio->positions)
        ->firstWhere('purpose', TreasuryPositionPurpose::ClientFunds);
    $issuerReserve = collect($issuerPortfolio->positions)
        ->firstWhere('purpose', TreasuryPositionPurpose::PayCodeReserve);
    app(VerifiedTreasuryFundingAllocationContract::class)->allocate(
        accountReference: 'wallet:'.$issuer->wallet->uuid,
        provider: 'netbank',
        amountMinor: 100_000,
        currency: 'PHP',
        evidenceReference: 'stored-value-acceptance:funding',
    );
    app(TreasuryPositionOperationContract::class)->reserve(new TreasuryPositionReservationData(
        operationReference: 'stored-value-acceptance:reservation',
        sourcePositionReference: $issuerClientFunds->positionReference,
        destinationPositionReference: $issuerReserve->positionReference,
        amountMinor: 100_000,
        currency: 'PHP',
        idempotencyKey: 'stored-value-acceptance:reservation:key',
        externalReference: 'stored-value-acceptance:voucher',
    ));
    $voucher = storedValueAcceptanceVoucher();
    $instruction = ExecutionInstructionData::from(
        data_get($voucher->metadata, 'instructions.execution'),
    );
    $this->actingAs($holder);
    $activation = app(ExecutionEngine::class)->execute(new ExecutionContextData(
        contact: new Contact(['mobile' => '+639173011987', 'country' => 'PH']),
        voucherCode: $voucher->code,
        voucher: $voucher,
        instruction: $instruction,
        correlation: ['execution_id' => 'stored-value-acceptance:activation'],
    ));
    $binding = StoredValueHolderBinding::query()->where('voucher_id', $voucher->getKey())->sole();
    $credential = app(CreatePartnerApiClient::class)->handle(
        name: 'Transit Merchant Acceptance',
        issuer: $merchant,
        scopes: ['stored-value:spend', 'stored-value:read'],
        mandate: [
            'stored_value_spend' => [
                'enabled' => true,
                'currencies' => ['PHP'],
                'maximum_amount_minor' => 10_000,
                'daily_amount_minor' => 100_000,
            ],
        ],
    );
    $oauth = Client::query()->findOrFail($credential->client_id);
    Passport::actingAsClient($oauth, ['stored-value:spend', 'stored-value:read']);
    $otp = new FakeOtpChallengeGateway;
    $this->app->instance(OtpChallengeGateway::class, $otp);
    $inventoryBalanceBefore = (int) TreasuryInventory::query()->sum('balance_minor');
    $challengeUrl = '/api/partner/v1/stored-value-instruments/'.$binding->reference.'/spend-challenges';
    $challengeReference = $this->postJson($challengeUrl, [
        'amount_minor' => 2_500,
        'currency' => 'PHP',
    ], ['Idempotency-Key' => 'stored-value-acceptance:challenge'])
        ->assertCreated()
        ->json('data.reference');
    $this->postJson($challengeUrl.'/'.$challengeReference.'/verification', ['code' => '000000'])
        ->assertOk()
        ->assertJsonPath('data.status', 'verified');
    $spendUrl = '/api/partner/v1/stored-value-instruments/'.$binding->reference.'/spends';
    $spendPayload = [
        'amount_minor' => 2_500,
        'currency' => 'PHP',
        'otp_challenge_reference' => $challengeReference,
    ];
    $spendHeaders = ['Idempotency-Key' => 'stored-value-acceptance:fare'];

    $first = $this->postJson($spendUrl, $spendPayload, $spendHeaders)
        ->assertCreated()
        ->assertJsonPath('data.transaction.balance_after_minor', 97_500)
        ->json('data');
    $allocation = app(TreasuryAllocationReadModelContract::class)->read(
        new TreasuryAllocationReadModelQueryData($binding->allocation_reference, 'PHP'),
    );
    $drawCount = TreasuryAllocationOperation::query()
        ->where('operation_type', TreasuryAllocationOperationType::Draw)
        ->count();
    $merchantBalance = treasuryClientFundsLedger($merchant)->getBalanceIntAttribute();

    $this->postJson($spendUrl, $spendPayload, $spendHeaders)
        ->assertOk()
        ->assertJsonPath('data', $first)
        ->assertJsonPath('meta.idempotency.replayed', true);

    expect($activation->successful)->toBeTrue()
        ->and($allocation->usableAmountMinor)->toBe(97_500)
        ->and($allocation->drawnAmountMinor)->toBe(2_500)
        ->and($merchantBalance)->toBe(2_500)
        ->and(treasuryClientFundsLedger($merchant)->getBalanceIntAttribute())->toBe($merchantBalance)
        ->and(TreasuryAllocationOperation::query()
            ->where('operation_type', TreasuryAllocationOperationType::Draw)
            ->count())->toBe($drawCount)
        ->and(PartnerApiOperation::query()->count())->toBe(1)
        ->and((int) TreasuryInventory::query()->sum('balance_minor'))->toBe($inventoryBalanceBefore);
});

function storedValueAcceptanceUser(string $name, ?string $mobile = null): User
{
    $user = User::query()->create([
        'name' => $name,
        'email' => str()->slug($name).'-'.str()->uuid().'@example.test',
        'password' => Hash::make('password'),
    ]);
    $user->forceFill([
        'mobile' => $mobile,
        'mobile_verified_at' => $mobile !== null ? now()->utc() : null,
    ])->save();
    fundTestUserWallet($user, 0);

    return $user;
}

function storedValueAcceptanceVoucher(): Voucher
{
    return Voucher::query()->create([
        'code' => 'SVAC',
        'state' => 'active',
        'metadata' => [
            'treasury' => [
                'pay_code_reservation' => [
                    'status' => 'reserved',
                    'operation_reference' => 'stored-value-acceptance:reservation',
                    'amount_minor' => 100_000,
                    'currency' => 'PHP',
                ],
            ],
            'instructions' => [
                'execution' => [
                    'schema' => 'voucher.execution.v1',
                    'driver' => 'stored_value',
                    'metadata' => [
                        'post_redemption' => ['mode' => 'execution_only'],
                        'stored_value' => [
                            'initial_balance' => 100_000,
                            'max_balance' => 100_000,
                            'replenishable' => false,
                            'otp_required_above' => 1_000,
                        ],
                    ],
                ],
            ],
        ],
    ]);
}
