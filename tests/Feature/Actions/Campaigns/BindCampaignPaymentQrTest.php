<?php

declare(strict_types=1);

use Bavix\Wallet\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use LBHurtado\EmiCore\Enums\FundingAddressPurpose;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Treasury\Models\TreasuryInventoryOperation;
use LBHurtado\XChange\Actions\Campaigns\BindCampaignPaymentQr;
use LBHurtado\XChange\Actions\Leads\CreateLeadCampaign;
use LBHurtado\XChange\Actions\Payment\InspectCampaignPaymentEvidence;
use LBHurtado\XChange\Enums\CampaignEntryMode;
use LBHurtado\XChange\Enums\CampaignPaymentAmountMode;
use LBHurtado\XChange\Enums\FundingAddressStatus;
use LBHurtado\XChange\Enums\FundingRecognitionMode;
use LBHurtado\XChange\Models\AccountFundingReceipt;
use LBHurtado\XChange\Models\CampaignPaymentEvidenceQuarantine;
use LBHurtado\XChange\Models\CampaignPaymentQrBinding;
use LBHurtado\XChange\Models\FundingSettlement;
use LBHurtado\XChange\Models\LeadCampaign;
use LBHurtado\XChange\Models\PayCodeTemplate;
use LBHurtado\XChange\Models\StandingFundingAddress;
use LBHurtado\XChange\Models\StandingFundingQrArtifact;
use LBHurtado\XChange\Services\Cockpit\CampaignPaymentEvidenceAttentionReadModel;
use LBHurtado\XChange\Tests\Fakes\User;

it('immutably binds one reusable payment QR to one campaign revision', function (): void {
    $owner = campaignPaymentQrOwner();
    $campaign = campaignPaymentQrCampaign($owner, CampaignEntryMode::ReusablePaymentQr);
    [$address, $artifact] = campaignPaymentQrAddress($owner);
    $availableFrom = CarbonImmutable::parse('2026-09-23T00:00:00Z');
    $availableUntil = CarbonImmutable::parse('2026-10-23T00:00:00Z');

    $binding = app(BindCampaignPaymentQr::class)->handle(
        owner: $owner,
        campaign: $campaign,
        address: $address,
        artifact: $artifact,
        amountMode: CampaignPaymentAmountMode::Fixed,
        fixedAmountMinor: 12_200,
        availableFrom: $availableFrom,
        availableUntil: $availableUntil,
        permittedPaymentRules: [
            'maximum_payments' => 500,
            'allowed_rails' => ['INSTAPAY'],
        ],
    );
    $replayed = app(BindCampaignPaymentQr::class)->handle(
        owner: $owner,
        campaign: $campaign,
        address: $address,
        artifact: $artifact,
        amountMode: CampaignPaymentAmountMode::Fixed,
        fixedAmountMinor: 12_200,
        availableFrom: $availableFrom,
        availableUntil: $availableUntil,
        permittedPaymentRules: [
            'allowed_rails' => ['INSTAPAY'],
            'maximum_payments' => 500,
        ],
    );

    expect($binding->entry_mode)->toBe(CampaignEntryMode::ReusablePaymentQr)
        ->and($binding->amount_mode)->toBe(CampaignPaymentAmountMode::Fixed)
        ->and($binding->fixed_amount_minor)->toBe(12_200)
        ->and($binding->campaign_revision_id)->toBe($campaign->active_template_version_id)
        ->and($binding->campaign->is($campaign))->toBeTrue()
        ->and($binding->standingFundingAddress->is($address))->toBeTrue()
        ->and($binding->qrArtifact->is($artifact))->toBeTrue()
        ->and($replayed->is($binding))->toBeTrue()
        ->and(CampaignPaymentQrBinding::query()->count())->toBe(1);

    expect(fn () => $binding->forceFill(['fixed_amount_minor' => 1])->save())
        ->toThrow(LogicException::class, 'immutable');
    expect(fn () => $binding->delete())
        ->toThrow(LogicException::class, 'cannot be deleted');
});

it('rejects ordinary endpoint campaigns and conflicting revision bindings', function (): void {
    $owner = campaignPaymentQrOwner();
    $ordinary = campaignPaymentQrCampaign($owner, CampaignEntryMode::PayCodeOnOpen);
    [$address, $artifact] = campaignPaymentQrAddress($owner);

    expect(fn () => app(BindCampaignPaymentQr::class)->handle(
        owner: $owner,
        campaign: $ordinary,
        address: $address,
        artifact: $artifact,
        amountMode: CampaignPaymentAmountMode::Open,
    ))->toThrow(ValidationException::class, 'reusable-payment-QR');

    $campaign = campaignPaymentQrCampaign($owner, CampaignEntryMode::ReusablePaymentQr);
    $action = app(BindCampaignPaymentQr::class);
    $action->handle(
        owner: $owner,
        campaign: $campaign,
        address: $address,
        artifact: $artifact,
        amountMode: CampaignPaymentAmountMode::Open,
        permittedPaymentRules: ['allowed_rails' => ['INSTAPAY']],
    );

    expect(fn () => $action->handle(
        owner: $owner,
        campaign: $campaign,
        address: $address,
        artifact: $artifact,
        amountMode: CampaignPaymentAmountMode::Open,
        permittedPaymentRules: ['allowed_rails' => ['PESONET']],
    ))->toThrow(ValidationException::class, 'already bound');
});

it('requires an active payment-purpose static QR and coherent amount availability', function (): void {
    $owner = campaignPaymentQrOwner();
    $campaign = campaignPaymentQrCampaign($owner, CampaignEntryMode::ReusablePaymentQr);
    [$address, $artifact] = campaignPaymentQrAddress($owner, FundingAddressPurpose::AccountFunding);

    expect(fn () => app(BindCampaignPaymentQr::class)->handle(
        owner: $owner,
        campaign: $campaign,
        address: $address,
        artifact: $artifact,
        amountMode: CampaignPaymentAmountMode::Fixed,
        fixedAmountMinor: 0,
        availableFrom: CarbonImmutable::parse('2026-09-24T00:00:00Z'),
        availableUntil: CarbonImmutable::parse('2026-09-23T00:00:00Z'),
    ))->toThrow(ValidationException::class, 'payment-purpose QR');
});

it('quarantines adverse and incompatible campaign payment evidence without financial effects', function (): void {
    $owner = campaignPaymentQrOwner();
    $campaign = campaignPaymentQrCampaign($owner, CampaignEntryMode::ReusablePaymentQr);
    [$address, $artifact] = campaignPaymentQrAddress($owner);
    $binding = app(BindCampaignPaymentQr::class)->handle(
        owner: $owner,
        campaign: $campaign,
        address: $address,
        artifact: $artifact,
        amountMode: CampaignPaymentAmountMode::Open,
    );
    $before = campaignPaymentFinancialCounts();

    $returned = campaignPaymentObservation($address, 'transaction-returned', 'returned');
    $first = app(InspectCampaignPaymentEvidence::class)->handle($binding, $returned);
    $replayed = app(InspectCampaignPaymentEvidence::class)->handle($binding, $returned);
    $settled = campaignPaymentObservation($address, 'transaction-conflict', 'settled');
    campaignPaymentObservation($address, 'transaction-conflict', 'returned');
    $incompatible = app(InspectCampaignPaymentEvidence::class)->handle($binding, $settled);

    expect($first)->not->toBeNull()
        ->and($first?->reason_code)->toBe('adverse_status')
        ->and($first?->reason_detail)->toBe('returned')
        ->and($replayed?->is($first))->toBeTrue()
        ->and($incompatible?->reason_code)->toBe('incompatible_evidence')
        ->and($incompatible?->reason_detail)->toBe('status_transition:settled:returned')
        ->and(CampaignPaymentEvidenceQuarantine::query()->count())->toBe(2)
        ->and(campaignPaymentFinancialCounts())->toBe($before);

    expect(fn () => $first?->forceFill(['reason_code' => 'changed'])->save())
        ->toThrow(LogicException::class, 'immutable');
    expect(fn () => $first?->delete())
        ->toThrow(LogicException::class, 'cannot be deleted');
});

it('keeps compatible evidence clear and exposes only aggregate campaign attention', function (): void {
    $owner = campaignPaymentQrOwner();
    $campaign = campaignPaymentQrCampaign($owner, CampaignEntryMode::ReusablePaymentQr);
    [$address, $artifact] = campaignPaymentQrAddress($owner);
    $binding = app(BindCampaignPaymentQr::class)->handle(
        owner: $owner,
        campaign: $campaign,
        address: $address,
        artifact: $artifact,
        amountMode: CampaignPaymentAmountMode::Open,
    );

    $settled = campaignPaymentObservation($address, 'transaction-settled', 'settled');
    $unknown = campaignPaymentObservation($address, 'transaction-unknown', 'mystery');

    expect(app(InspectCampaignPaymentEvidence::class)->handle($binding, $settled))->toBeNull();
    app(InspectCampaignPaymentEvidence::class)->handle($binding, $unknown);

    $attention = app(CampaignPaymentEvidenceAttentionReadModel::class)
        ->forCampaigns(LeadCampaign::query()->whereKey($campaign->getKey())->get());
    $row = $attention[$campaign->getKey()];

    expect($row)->toMatchArray([
        'count' => 1,
        'status' => 'needs_attention',
        'label' => 'Needs attention',
        'latest_reason' => 'unknown_status',
    ])
        ->and(json_encode($row))->not->toContain('transaction-unknown')
        ->and(json_encode($row))->not->toContain('provider_transaction');
});

function campaignPaymentQrOwner(): User
{
    $owner = actingAsTestUser();
    $owner->forceFill(['name' => 'AUI Insurance'])->save();

    return $owner;
}

function campaignPaymentQrCampaign(User $owner, CampaignEntryMode $entryMode): LeadCampaign
{
    $template = PayCodeTemplate::query()->create([
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => (string) $owner->getKey(),
        'name' => 'Campaign payment QR template '.str()->ulid(),
        'base_template_key' => 'blank-pay-code',
        'instructions_ciphertext' => [
            'cash' => ['amount' => 0, 'currency' => 'PHP'],
            'count' => 1,
            'prefix' => 'AUI',
            'mask' => '****',
        ],
        'include_amount' => true,
        'include_purpose' => true,
        'status' => 'active',
    ]);

    return app(CreateLeadCampaign::class)->handle($owner, $template, [
        'title' => 'AUI payment campaign '.str()->ulid(),
        'settings' => ['entry_mode' => $entryMode->value],
    ]);
}

/**
 * @return array{StandingFundingAddress, StandingFundingQrArtifact}
 */
function campaignPaymentQrAddress(
    User $owner,
    FundingAddressPurpose $purpose = FundingAddressPurpose::Payment,
): array {
    $addressValue = '91500'.str_pad((string) random_int(1, 9999999999999999), 16, '0', STR_PAD_LEFT);
    $address = StandingFundingAddress::query()->create([
        'binding_key' => hash('sha256', 'campaign-payment-qr-binding-'.$addressValue),
        'owner_type' => $owner::class,
        'owner_id' => $owner->getKey(),
        'account_reference' => 'campaign:'.$owner->getKey(),
        'provider_code' => 'netbank',
        'purpose' => $purpose,
        'recognition_mode' => FundingRecognitionMode::ObserveOnly,
        'status' => FundingAddressStatus::Active,
        'version' => 1,
        'provider_reference' => 'standing:netbank:'.$addressValue,
        'funding_address_ciphertext' => $addressValue,
        'funding_address_hash' => hash('sha256', $addressValue),
        'currency' => 'PHP',
        'activated_at' => now(),
        'metadata' => ['reusable' => true],
    ]);
    $artifact = $address->qrArtifacts()->create([
        'status' => 'active',
        'version' => 1,
        'artifact_fingerprint' => hash('sha256', 'artifact-'.$addressValue),
        'mime_type' => 'image/png',
        'qr_mode' => 'Static',
        'transaction_type' => 'P2M',
        'embedded_amount' => false,
        'provider_generated' => true,
        'payload_ciphertext' => base64_encode('campaign-payment-qr'),
        'display_snapshot_ciphertext' => ['provider' => 'netbank'],
        'generated_at' => now(),
    ]);

    return [$address, $artifact];
}

function campaignPaymentObservation(
    StandingFundingAddress $address,
    string $transactionId,
    string $status,
): ProviderFundingObservation {
    return ProviderFundingObservation::query()->create([
        'observation_key' => hash('sha256', $transactionId.'-'.$status),
        'provider_code' => 'netbank',
        'provider_transaction_id' => $transactionId,
        'provider_operation_id' => 'operation-'.$transactionId,
        'funding_address' => 'sha256:'.$address->funding_address_hash,
        'provider_account_reference' => 'sha256:'.hash('sha256', 'campaign-provider-account'),
        'gross_amount_minor' => 12_200,
        'fee_amount_minor' => 0,
        'net_amount_minor' => 12_200,
        'currency' => 'PHP',
        'provider_status' => $status,
        'occurred_at' => CarbonImmutable::parse('2026-09-23T01:00:00Z'),
        'settled_at' => $status === 'settled'
            ? CarbonImmutable::parse('2026-09-23T01:01:00Z')
            : null,
        'verification_source' => 'campaign-payment-test',
        'payload_hash' => hash('sha256', $transactionId.'-'.$status.'-payload'),
        'metadata' => [
            'destination_verified' => true,
            'settlement_rail' => 'INSTAPAY',
            'normalization_version' => 'test-v1',
        ],
    ]);
}

/**
 * @return array{account_funding_receipts: int, funding_settlements: int, vouchers: int, wallet_transactions: int, treasury_operations: int}
 */
function campaignPaymentFinancialCounts(): array
{
    return [
        'account_funding_receipts' => AccountFundingReceipt::query()->count(),
        'funding_settlements' => FundingSettlement::query()->count(),
        'vouchers' => Voucher::query()->count(),
        'wallet_transactions' => Transaction::query()->count(),
        'treasury_operations' => TreasuryInventoryOperation::query()->count(),
    ];
}
