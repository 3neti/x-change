<?php

declare(strict_types=1);

use Composer\InstalledVersions;
use LBHurtado\Voucher\Services\VoucherSlicePlanFactory;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryAllocationReadModelContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationReadModelData;
use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationReadModelQueryData;
use LBHurtado\XChange\Contracts\VoucherLifecycleServiceContract;
use LBHurtado\XChange\Exceptions\VoucherNotFound;
use LBHurtado\XChange\Models\StoredValueHolderBinding;
use LBHurtado\XChange\Models\VoucherCollection;

it('returns guest-safe x-ray disclosure for a pay code', function (): void {
    $service = Mockery::mock(VoucherLifecycleServiceContract::class);
    $service->shouldReceive('showByCode')
        ->once()
        ->with('XRAY-1234')
        ->andReturn((object) [
            'code' => 'XRAY-1234',
            'amount' => 1500.00,
            'currency' => 'PHP',
            'status' => 'issued',
            'issuer_id' => 7,
            'claimed' => false,
            'fully_claimed' => false,
            'instructions' => [
                'cash' => [
                    'currency' => 'PHP',
                ],
                'inputs' => [
                    'fields' => ['mobile', 'bank_account'],
                ],
                'rider' => [
                    'message' => 'Check the details before claiming.',
                ],
            ],
        ]);

    $this->app->instance(VoucherLifecycleServiceContract::class, $service);

    $response = $this->postJson(xchangeApi('pay-codes/x-ray'), [
        'code' => 'xray-1234',
        'channel' => 'claim',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.xray.visible', true)
        ->assertJsonPath('data.xray.status', 'claimable')
        ->assertJsonPath('data.xray.presentation.title', 'Claim Pay Code')
        ->assertJsonPath('data.xray.presentation.primary_action_label', 'Start Claim')
        ->assertJsonPath('data.xray.disclosures.0.key', 'status')
        ->assertJsonPath('data.xray.requirements.0.key', 'mobile')
        ->assertJsonPath('data.xray.requirements.1.key', 'bank_account');

    expect(collect($response->json('data.xray.redactions'))->pluck('key')->all())
        ->toContain('amount', 'issuer', 'redirect_url');
});

it('shows an issued Pay Code amount, issuer and recorded payout provider to a guest', function (): void {
    config()->set('x-change.claim.experience_ui.show_package_versions', false);
    config()->set('x-change.claim.experience_ui.system_info.authors', ['Ada', 'Anaïs']);
    config()->set('x-change.claim.experience_ui.system_info.contributors', ['Ada']);
    $issuer = actingAsTestUser();
    $issuer->forceFill(['name' => 'Amelia Cruz'])->save();
    $voucher = issueVoucher(validVoucherInstructions(amount: 50));
    $metadata = (array) $voucher->metadata;
    data_set($metadata, 'treasury.pay_code_reservation.provider', 'netbank');
    $voucher->forceFill(['metadata' => $metadata])->save();
    auth()->logout();

    $response = $this->postJson(xchangeApi('pay-codes/x-ray'), [
        'code' => $voucher->code,
        'channel' => 'claim',
    ])->assertOk();

    $details = collect($response->json('data.xray.disclosures'))->keyBy('key');

    expect($details->get('amount')['value'])->toBe('₱50.00')
        ->and($details->get('value_use')['value'])->toMatchArray(['mode' => 'whole', 'amount_basis' => 'whole', 'amount_minor' => 5_000])
        ->and($details->get('issuer')['value'])->toBe('Amelia Cruz')
        ->and($details->get('provider')['value'])->toBe('NetBank')
        ->and($details->get('funding_source')['value'])->toBe('Pay Code reserve')
        ->and($details->get('reference')['value'])->toBe($voucher->code)
        ->and($details->get('service_info')['value']['publisher'])->toBe('3neti')
        ->and($details->get('service_info')['value']['authors'])->toBe(['Ada', 'Anaïs'])
        ->and($details->get('service_info')['value']['contributors'])->toBe(['Ada'])
        ->and($details->get('service_info')['value'])->not->toHaveKey('x_change_version')
        ->and($details->get('service_info')['value'])->not->toHaveKey('x_ray_version');

    config()->set('x-change.claim.experience_ui.show_package_versions', true);

    $versionedResponse = $this->postJson(xchangeApi('pay-codes/x-ray'), [
        'code' => $voucher->code,
        'channel' => 'claim',
    ])->assertOk();

    $versionedServiceInfo = collect($versionedResponse->json('data.xray.disclosures'))
        ->keyBy('key')
        ->get('service_info')['value'];

    expect($versionedServiceInfo['x_ray_version'])
        ->toBe(InstalledVersions::getPrettyVersion('3neti/x-ray'));
});

it('does not infer a payout provider from an unreserved Pay Code', function (): void {
    actingAsTestUser();
    $voucher = issueVoucher(validVoucherInstructions(amount: 50));
    auth()->logout();

    $response = $this->postJson(xchangeApi('pay-codes/x-ray'), [
        'code' => $voucher->code,
    ])->assertOk();

    expect(collect($response->json('data.xray.disclosures'))->pluck('key')->all())
        ->not->toContain('provider', 'funding_source');
});

it('keeps confirmed collection paid in x-ray after the voucher expires', function (): void {
    $issuer = actingAsTestUser();
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'voucher_type' => 'payable',
        'target_amount' => 100,
        'metadata' => [
            'flow_type' => 'collectible',
            'collection_wallet_id' => (string) $issuer->wallet()->where('slug', 'platform')->sole()->getKey(),
        ],
    ]));
    VoucherCollection::query()->create([
        'voucher_id' => $voucher->getKey(),
        'collection_number' => 1,
        'status' => 'collected',
        'requested_amount_minor' => 10000,
        'collected_amount_minor' => 10000,
        'currency' => 'PHP',
        'provider' => 'netbank',
        'provider_reference' => 'xray-paid-expired',
        'provider_transaction_id' => 'xray-paid-expired',
        'idempotency_key' => 'xray-paid-expired',
        'completed_at' => now()->subDay(),
    ]);
    $voucher->forceFill(['expires_at' => now()->subHour()])->save();
    auth()->logout();

    $this->postJson(xchangeApi('pay-codes/x-ray'), ['code' => $voucher->code])
        ->assertOk()
        ->assertJsonPath('data.xray.status', 'paid');
});

it('returns a safe x-ray not found response', function (): void {
    $service = Mockery::mock(VoucherLifecycleServiceContract::class);
    $service->shouldReceive('showByCode')
        ->once()
        ->with('MISSING')
        ->andThrow(new VoucherNotFound('Voucher not found.'));

    $this->app->instance(VoucherLifecycleServiceContract::class, $service);

    $this->postJson(xchangeApi('pay-codes/x-ray'), [
        'code' => 'MISSING',
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.xray.visible', false)
        ->assertJsonPath('data.xray.status', 'not_found')
        ->assertJsonPath('data.xray.requirements', []);
});

it('discloses canonical slice labels without retired cash slice fields', function (): void {
    config()->set('x-ray.disclosure.guest.show_remaining_slices', 'if_allowed_by_voucher');
    actingAsTestUser();
    $plan = app(VoucherSlicePlanFactory::class)->equal(7_500, 'PHP', 3);
    $voucher = issueVoucher(validVoucherInstructions(
        amount: 75,
        overrides: ['slice_plan' => $plan->canonicalArray()],
    ));
    auth()->logout();

    $response = $this->postJson(xchangeApi('pay-codes/x-ray'), [
        'code' => $voucher->code,
        'channel' => 'claim',
    ])->assertOk();

    $sliceDisclosure = collect($response->json('data.xray.disclosures'))
        ->firstWhere('key', 'remaining_slices');

    expect($sliceDisclosure)->toBeArray()
        ->and($sliceDisclosure['value'])->toHaveCount(3)
        ->and(collect($sliceDisclosure['value'])->pluck('label')->all())
        ->toBe(['Slice 1', 'Slice 2', 'Slice 3'])
        ->and(data_get($sliceDisclosure, 'value.0.amount_minor'))->toBe(2_500);

    expect(collect($response->json('data.xray.disclosures'))->firstWhere('key', 'value_use')['value'])
        ->toMatchArray(['mode' => 'equal', 'amount_basis' => 'available_now', 'amount_minor' => 2_500, 'portion_number' => 1, 'portion_count' => 3]);
});

it('discloses remaining flexible balance and the next scheduled portion rather than face value', function (): void {
    actingAsTestUser();
    $factory = app(VoucherSlicePlanFactory::class);
    $flexible = issueVoucher(validVoucherInstructions(
        amount: 100,
        overrides: ['slice_plan' => $factory->flexible(10_000, 'PHP', 4, 500)->canonicalArray()],
    ));
    $scheduled = issueVoucher(validVoucherInstructions(
        amount: 100,
        overrides: ['slice_plan' => $factory->scheduled(10_000, 'PHP', [
            ['amount_minor' => 4_000, 'claim_on' => now()->addDays(2)->toIso8601String()],
            ['amount_minor' => 6_000, 'claim_on' => now()->addDays(5)->toIso8601String()],
        ])->canonicalArray()],
    ));
    auth()->logout();

    $flexibleResponse = $this->postJson(xchangeApi('pay-codes/x-ray'), ['code' => $flexible->code])->assertOk();
    $scheduledResponse = $this->postJson(xchangeApi('pay-codes/x-ray'), ['code' => $scheduled->code])->assertOk();

    expect(collect($flexibleResponse->json('data.xray.disclosures'))->firstWhere('key', 'value_use')['value'])
        ->toMatchArray(['mode' => 'flexible', 'amount_basis' => 'remaining', 'amount_minor' => 10_000, 'minimum_minor' => 500, 'claims_remaining' => 4])
        ->and(collect($scheduledResponse->json('data.xray.disclosures'))->firstWhere('key', 'value_use')['value'])
        ->toMatchArray(['mode' => 'scheduled', 'amount_basis' => 'next_available', 'amount_minor' => 4_000, 'portion_number' => 1, 'portion_count' => 2]);
});

it('shows a starting stored value before activation and the Treasury balance afterward', function (): void {
    $issuer = actingAsTestUser();
    $voucher = issueVoucher(validVoucherInstructions(amount: 100));
    $metadata = (array) $voucher->metadata;
    data_set($metadata, 'instructions.execution.driver', 'stored_value');
    data_set($metadata, 'instructions.execution.metadata.stored_value', [
        'initial_balance' => 10_000,
        'otp_required_above' => 5_000,
    ]);
    $voucher->forceFill(['metadata' => $metadata])->save();
    auth()->logout();

    $before = $this->postJson(xchangeApi('pay-codes/x-ray'), ['code' => $voucher->code])->assertOk();
    expect(collect($before->json('data.xray.disclosures'))->firstWhere('key', 'value_use')['value'])
        ->toMatchArray(['mode' => 'stored_value', 'amount_basis' => 'starting', 'amount_minor' => 10_000]);

    StoredValueHolderBinding::query()->create([
        'voucher_id' => $voucher->getKey(),
        'allocation_reference' => 'allocation:test',
        'reservation_operation_reference' => 'reservation:test',
        'activation_operation_reference' => 'activation:test',
        'holder_type' => $issuer->getMorphClass(),
        'holder_id' => (string) $issuer->getKey(),
        'holder_principal_reference' => 'principal:test',
        'holder_authority_reference' => 'authority:test',
        'currency' => 'PHP',
        'activated_at' => now(),
    ]);
    $allocations = new class implements TreasuryAllocationReadModelContract
    {
        public bool $hasFacts = true;

        public function read(TreasuryAllocationReadModelQueryData $query): TreasuryAllocationReadModelData
        {
            return new TreasuryAllocationReadModelData(
                allocationReference: $query->allocationReference,
                currency: 'PHP',
                allocatedAmountMinor: 10_000,
                drawnAmountMinor: 6_500,
                releasedAmountMinor: 0,
                outstandingAmountMinor: 6_500,
                usableAmountMinor: 3_500,
                sliceCount: 0,
                hasTreasuryFacts: $this->hasFacts,
                metadata: [],
            );
        }
    };
    $this->app->instance(TreasuryAllocationReadModelContract::class, $allocations);

    $after = $this->postJson(xchangeApi('pay-codes/x-ray'), ['code' => $voucher->code])->assertOk();
    expect(collect($after->json('data.xray.disclosures'))->firstWhere('key', 'value_use')['value'])
        ->toMatchArray(['mode' => 'stored_value', 'amount_basis' => 'remaining', 'amount_minor' => 3_500]);

    $allocations->hasFacts = false;
    $unavailable = $this->postJson(xchangeApi('pay-codes/x-ray'), ['code' => $voucher->code])->assertOk();
    $summary = collect($unavailable->json('data.xray.disclosures'))->firstWhere('key', 'value_use')['value'];
    expect($summary)->toMatchArray(['mode' => 'stored_value', 'amount_basis' => 'unavailable'])
        ->not->toHaveKey('amount_minor');
});

it('validates x-ray inspection requests', function (): void {
    $this->postJson(xchangeApi('pay-codes/x-ray'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);
});
