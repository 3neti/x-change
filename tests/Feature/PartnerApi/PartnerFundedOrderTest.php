<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use LBHurtado\EmiCore\Data\Funding\ProviderFundingObservationData;
use LBHurtado\EmiCore\Exceptions\ProviderFundingNotObserved;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Funding\RequestOnDemandPayCodeIssuanceRetry;
use LBHurtado\XChange\Actions\Funding\SettleVerifiedFundingIntent;
use LBHurtado\XChange\Actions\Funding\VerifyFundingIntent;
use LBHurtado\XChange\Actions\PartnerApi\CreatePartnerAccountFundedOrder;
use LBHurtado\XChange\Actions\PartnerApi\CreatePartnerApiClient;
use LBHurtado\XChange\Actions\PartnerApi\ReleasePartnerIssuanceReservation;
use LBHurtado\XChange\Actions\PayCode\EstimatePayCodeCost;
use LBHurtado\XChange\Contracts\AccountBalanceReadModelContract;
use LBHurtado\XChange\Data\DebitData;
use LBHurtado\XChange\Data\Funding\FundingIntentVerificationData;
use LBHurtado\XChange\Data\IssuerData;
use LBHurtado\XChange\Data\PayCode\GeneratePayCodeResultData;
use LBHurtado\XChange\Data\PayCodeLinksData;
use LBHurtado\XChange\Data\PricingEstimateData;
use LBHurtado\XChange\Enums\FundingVerificationTrigger;
use LBHurtado\XChange\Exceptions\ExternalReferenceConflict;
use LBHurtado\XChange\Exceptions\IdempotencyConflict;
use LBHurtado\XChange\Jobs\Funding\ResumeOnDemandPayCodeIssuanceJob;
use LBHurtado\XChange\Models\Checkout;
use LBHurtado\XChange\Models\CommercialOffering;
use LBHurtado\XChange\Models\CommercialPrincipal;
use LBHurtado\XChange\Models\FundingIntent;
use LBHurtado\XChange\Models\PartnerApiClient;
use LBHurtado\XChange\Models\PartnerApiOperation;
use LBHurtado\XChange\Models\PartnerApiPayCodeReference;
use LBHurtado\XChange\Models\PayCodeIssuanceFundingOrder;
use LBHurtado\XChange\Services\BuildBalanceOverview;
use LBHurtado\XChange\Services\Commercial\BootstrapCommercialOfferingFactory;
use LBHurtado\XChange\Services\Commercial\ProvisionCommercialBaselines;
use LBHurtado\XChange\Services\Funding\FundingProviderAdapterRegistry;
use LBHurtado\XChange\Services\PartnerApi\PartnerIssuanceReservationService;
use LBHurtado\XChange\Tests\Fakes\FakeFundingProviderAdapter;
use LBHurtado\XChange\Tests\Fakes\User;
use Symfony\Component\HttpKernel\Exception\HttpException;

function fundedOrderClient(array $scopes): PartnerApiClient
{
    $issuer = User::query()->create([
        'name' => 'Funded order agent',
        'email' => 'funded-'.str()->uuid().'@example.test',
        'password' => Hash::make('password'),
    ]);
    $credential = app(CreatePartnerApiClient::class)->handle(
        name: 'Funded order agent',
        issuer: $issuer,
        scopes: $scopes,
    );
    Passport::actingAsClient(Client::query()->findOrFail($credential->client_id), $scopes);

    return PartnerApiClient::query()->where('reference', $credential->reference)->sole();
}

function fundedOrderForClient(PartnerApiClient $client): PayCodeIssuanceFundingOrder
{
    $principal = CommercialPrincipal::query()->create([
        'reference' => 'funded-agent-principal',
        'legal_name' => 'Funded Agent Principal',
        'authorization_reference' => 'commissioning:funded-agent-principal:v1',
        'active' => true,
        'metadata' => ['interactive_login' => false],
    ]);
    $order = PayCodeIssuanceFundingOrder::query()->create([
        'account_reference' => 'wallet:funded-agent-test',
        'issuer_type' => CommercialPrincipal::class,
        'issuer_id' => (string) $principal->getKey(),
        'provider' => 'netbank',
        'connection_reference' => 'netbank-primary',
        'funding_basis' => 'full_amount',
        'instructions_ciphertext' => ['cash' => ['amount' => 50, 'currency' => 'PHP']],
        'instructions_fingerprint' => str_repeat('a', 64),
        'pricing_snapshot_ciphertext' => ['total' => 50],
        'pricing_fingerprint' => str_repeat('b', 64),
        'required_amount_minor' => 5000,
        'reserved_client_funds_minor' => 0,
        'on_demand_amount_minor' => 5000,
        'reconciliation_adjustment_minor' => 0,
        'expected_payment_minor' => 5000,
        'currency' => 'PHP',
        'status' => 'awaiting_payment',
        'version' => 1,
        'idempotency_key_hash' => str_repeat('c', 64),
        'idempotency_fingerprint' => str_repeat('d', 64),
        'expires_at' => now()->addMinutes(30),
        'metadata' => ['source' => 'partner.funded-order'],
    ]);
    Checkout::query()->create([
        'source' => 'partner.funded-order',
        'owner_type' => CommercialPrincipal::class,
        'owner_id' => (string) $principal->getKey(),
        'funding_order_id' => $order->getKey(),
        'partner_api_client_id' => $client->getKey(),
        'status' => 'placed',
        'selected_method' => 'qr_ph',
        'placed_at' => now(),
    ]);

    return $order;
}

function fundedReservationInstructions(int $amount): array
{
    return ['cash' => ['amount' => $amount, 'currency' => 'PHP', 'settlement_rail' => 'INSTAPAY', 'validation' => ['mobile' => '09171234567']]];
}

it('durably reserves references per client and rejects conflicting retries', function (): void {
    $client = fundedOrderClient(['funded-orders:create']);
    $reservations = app(PartnerIssuanceReservationService::class);
    $payload = fundedReservationInstructions(50);
    $first = $reservations->reserve($client, 'invoice-1', 'order-key', $payload, 5000, 'PHP');
    $replay = $reservations->reserve($client, 'invoice-1', 'order-key', $payload, 5000, 'PHP');

    expect($first['replayed'])->toBeFalse()->and($replay)->toBe(['id' => $first['id'], 'replayed' => true]);
    expect(fn () => $reservations->reserve($client, 'invoice-2', 'order-key', $payload, 5000, 'PHP'))
        ->toThrow(IdempotencyConflict::class);
    expect(fn () => $reservations->reserve($client, 'invoice-1', 'another-key', $payload, 5000, 'PHP'))
        ->toThrow(ExternalReferenceConflict::class);
    expect(fn () => $reservations->reserve($client, 'invoice-1', 'order-key', ['cash' => ['amount' => 60]], 6000, 'PHP'))
        ->toThrow(IdempotencyConflict::class);

    $other = fundedOrderClient(['funded-orders:create']);
    expect($reservations->reserve($other, 'invoice-1', 'order-key', $payload, 5000, 'PHP')['replayed'])->toBeFalse();
});

it('limits outstanding reservations without blocking a matching retry', function (): void {
    config()->set('x-change.partner_api.funded_orders.maximum_open_per_client', 1);
    $client = fundedOrderClient(['funded-orders:create']);
    $reservations = app(PartnerIssuanceReservationService::class);
    $payload = fundedReservationInstructions(50);
    $first = $reservations->reserve($client, 'limit-reference', 'limit-key', $payload, 5000, 'PHP');
    expect($reservations->reserve($client, 'limit-reference', 'limit-key', $payload, 5000, 'PHP')['id'])->toBe($first['id']);
    expect(fn () => $reservations->reserve($client, 'other-reference', 'other-key', $payload, 5000, 'PHP'))
        ->toThrow(HttpException::class, 'outstanding account-funded orders');
});

it('prevents direct issuance from taking a reserved reference', function (): void {
    $client = fundedOrderClient(['funded-orders:create', 'pay-codes:issue']);
    app(PartnerIssuanceReservationService::class)->reserve($client, 'reserved-1', 'order-key', fundedReservationInstructions(50), 5000, 'PHP');
    $payload = validVoucherInstructions(50, 'INSTAPAY')->toArray();
    unset($payload['slice_plan']);
    data_set($payload, 'cash.validation.mobile', '09171234567');
    $payload['external_reference'] = 'reserved-1';
    $this->postJson('/api/partner/v1/pay-codes', $payload, ['Idempotency-Key' => 'direct-key'])
        ->assertConflict()->assertJsonPath('code', 'EXTERNAL_REFERENCE_CONFLICT');
});

it('releases an expired partner reservation only after reviewed evidence and retains the reference', function (): void {
    $client = fundedOrderClient(['funded-orders:create']);
    $reservation = app(PartnerIssuanceReservationService::class)->reserve($client, 'release-1', 'release-key', fundedReservationInstructions(50), 5000, 'PHP');
    $order = fundedOrderForClient($client);
    $intent = FundingIntent::query()->create([
        'account_reference' => 'wallet:test', 'provider_code' => 'netbank', 'purpose' => 'on_demand_issuance',
        'expected_amount_minor' => 5000, 'currency' => 'PHP', 'status' => 'expired', 'version' => 1,
        'idempotency_key_hash' => hash('sha256', 'release-intent'), 'idempotency_fingerprint' => hash('sha256', 'release-intent-fingerprint'),
        'created_by_type' => $client->issuer->getMorphClass(), 'created_by_id' => (string) $client->issuer_id,
        'expires_at' => now()->subDays(8), 'instructions_created_at' => now()->subDays(9),
    ]);
    $order->forceFill(['status' => 'expired', 'funding_intent_id' => $intent->getKey(), 'expires_at' => now()->subDays(8)])->saveQuietly();
    DB::table('x_change_partner_issuance_reservations')->where('id', $reservation['id'])->update(['funding_order_id' => $order->getKey()]);
    $action = app(ReleasePartnerIssuanceReservation::class);
    expect(fn () => $action->handle($reservation['id'], $client->issuer, 'No payment found', 'bank-history-1'))->toThrow(ValidationException::class);
    $adapter = new FakeFundingProviderAdapter;
    $adapter->fundingVerificationResolver = static function (): never {
        throw new ProviderFundingNotObserved('No payment was observed.');
    };
    $this->app->instance(FakeFundingProviderAdapter::class, $adapter);
    $this->app->tag(FakeFundingProviderAdapter::class, 'emi.funding-provider-adapters');
    $this->app->forgetInstance(FundingProviderAdapterRegistry::class);
    Gate::define('x-change.recovery.manage', fn () => true);
    $this->actingAs($client->issuer)->post(route('x-change.operations.recovery.partner-reservation.verify', ['reservation' => $reservation['id']]))
        ->assertRedirect()->assertSessionHas('status');
    expect(DB::table('x_change_funding_reconciliation_checks')->where('funding_intent_id', $intent->getKey())->value('last_outcome'))->toBe('not_observed');
    DB::table('x_change_funding_reconciliation_checks')->where('funding_intent_id', $intent->getKey())->update(['last_checked_at' => now()->subHours(12), 'last_outcome' => 'unavailable']);
    expect(fn () => $action->handle($reservation['id'], $client->issuer, 'No payment found', 'bank-history-1'))->toThrow(ValidationException::class);
    DB::table('x_change_funding_reconciliation_checks')->where('funding_intent_id', $intent->getKey())->update(['last_outcome' => 'not_observed', 'last_checked_at' => now()->subDays(2)]);
    expect(fn () => $action->handle($reservation['id'], $client->issuer, 'No payment found', 'bank-history-1'))->toThrow(ValidationException::class);
    DB::table('x_change_funding_reconciliation_checks')->where('funding_intent_id', $intent->getKey())->update(['last_checked_at' => now()->subHours(12)]);
    $this->actingAs($client->issuer)->getJson(route('x-change.operations.recovery.partner-reservation.index'))
        ->assertOk()->assertJsonPath('data.0.id', $reservation['id']);
    $this->post(route('x-change.operations.recovery.partner-reservation.release', ['reservation' => $reservation['id']]), [
        'reason' => 'No payment found', 'evidence_reference' => 'bank-history-1',
    ])->assertRedirect()->assertSessionHas('status');
    $this->getJson(route('x-change.operations.recovery.partner-reservation.index'))->assertOk()->assertJsonPath('data', []);
    $released = DB::table('x_change_partner_issuance_reservations')->where('id', $reservation['id'])->first();
    expect($released->status)->toBe('released')->and($released->release_evidence_reference)->toBe('bank-history-1')
        ->and($released->released_by_id)->toBe((string) $client->issuer_id);
    expect(fn () => $action->handle($reservation['id'], $client->issuer, 'No payment found', 'bank-history-1'))->toThrow(ValidationException::class);
    expect(fn () => app(PartnerIssuanceReservationService::class)->reserve($client, 'release-1', 'another-key', fundedReservationInstructions(50), 5000, 'PHP'))->toThrow(ExternalReferenceConflict::class);
});

it('requires operator authorization to release a partner reservation', function (): void {
    $client = fundedOrderClient(['funded-orders:create']);
    Gate::define('x-change.recovery.manage', fn () => false);
    $this->actingAs($client->issuer)->getJson(route('x-change.operations.recovery.partner-reservation.index'))->assertForbidden();
    $this->post(route('x-change.operations.recovery.partner-reservation.verify', ['reservation' => 1]))->assertForbidden();
    $this->actingAs($client->issuer)->post(route('x-change.operations.recovery.partner-reservation.release', ['reservation' => 1]), [
        'reason' => 'No payment found', 'evidence_reference' => 'bank-history-1',
    ])->assertForbidden();
    Gate::define('x-change.recovery.manage', fn () => true);
    $this->getJson(route('x-change.operations.recovery.partner-reservation.index'))->assertOk()->assertJsonPath('data', []);
});

it('prevents a funding reservation from taking an already issued reference', function (): void {
    $client = fundedOrderClient(['funded-orders:create']);
    $voucher = new Voucher(['code' => 'RSVD', 'state' => 'active']);
    $voucher->owner()->associate($client->issuer);
    $voucher->save();
    PartnerApiPayCodeReference::query()->create([
        'partner_api_client_id' => $client->getKey(), 'external_reference' => 'already-issued',
        'voucher_id' => $voucher->getKey(), 'terms_hash' => str_repeat('a', 64),
    ]);

    expect(fn () => app(PartnerIssuanceReservationService::class)->reserve($client, 'already-issued', 'new-order', [], 5000, 'PHP'))
        ->toThrow(ExternalReferenceConflict::class);
});

it('counts pending reservations against the daily principal limit for new reservations and direct issuance', function (): void {
    $client = fundedOrderClient(['funded-orders:create', 'pay-codes:issue']);
    $mandate = $client->mandate;
    $mandate['daily_principal_limit_minor'] = 5000;
    $client->forceFill(['mandate' => $mandate])->save();
    $reservations = app(PartnerIssuanceReservationService::class);
    $first = $reservations->reserve($client, 'limit-1', 'key-1', fundedReservationInstructions(30), 3000, 'PHP');
    expect($reservations->reserve($client, 'limit-1', 'key-1', fundedReservationInstructions(30), 3000, 'PHP')['id'])->toBe($first['id']);
    expect(fn () => $reservations->reserve($client, 'limit-2', 'key-2', fundedReservationInstructions(25), 2500, 'PHP'))
        ->toThrow(ValidationException::class);

    $payload = validVoucherInstructions(21, 'INSTAPAY')->toArray();
    unset($payload['slice_plan']);
    data_set($payload, 'cash.validation.mobile', '09171234567');
    $payload['external_reference'] = 'direct-over-limit';
    $this->postJson('/api/partner/v1/pay-codes', $payload, ['Idempotency-Key' => 'direct-over-limit'])
        ->assertUnprocessable()->assertJsonValidationErrors('cash.amount');
});

it('serializes concurrent partner reservations against the daily principal limit', function (): void {
    if (! in_array(DB::connection()->getDriverName(), ['mysql', 'pgsql'], true) || ! function_exists('pcntl_fork')) {
        test()->markTestSkipped('Requires a dedicated MySQL or PostgreSQL database and pcntl.');
    }

    $client = fundedOrderClient(['funded-orders:create']);
    $mandate = $client->mandate;
    $mandate['daily_principal_limit_minor'] = 5000;
    $client->forceFill(['mandate' => $mandate])->save();
    $children = [];
    $channels = [];
    $paths = [];
    foreach ([1, 2] as $number) {
        $channel = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        $path = tempnam(sys_get_temp_dir(), 'partner-reservation-race-');
        if ($channel === false || $path === false) {
            throw new RuntimeException('Unable to prepare the Partner reservation race.');
        }
        $pid = pcntl_fork();
        if ($pid === -1) {
            throw new RuntimeException('Unable to start the Partner reservation race.');
        }
        if ($pid === 0) {
            fclose($channel[0]);
            DB::purge();
            fread($channel[1], 1);
            try {
                app(PartnerIssuanceReservationService::class)->reserve($client, 'race-'.$number, 'race-key-'.$number, fundedReservationInstructions(30), 3000, 'PHP');
                file_put_contents($path, 'reserved');
            } catch (ValidationException) {
                file_put_contents($path, 'limit');
            } catch (Throwable $exception) {
                file_put_contents($path, get_class($exception).': '.$exception->getMessage());
                exit(1);
            }
            exit(0);
        }
        fclose($channel[1]);
        $children[] = $pid;
        $channels[] = $channel[0];
        $paths[] = $path;
    }
    foreach ($channels as $channel) {
        fwrite($channel, '1');
        fclose($channel);
    }
    $exitCodes = [];
    foreach ($children as $pid) {
        pcntl_waitpid($pid, $status);
        $exitCodes[] = pcntl_wexitstatus($status);
    }
    $results = array_map(static fn (string $path): string => (string) file_get_contents($path), $paths);
    foreach ($paths as $path) {
        unlink($path);
    }
    DB::purge();
    sort($results);
    expect($exitCodes)->toBe([0, 0], implode('; ', $results))
        ->and($results)->toBe(['limit', 'reserved'])
        ->and(DB::table('x_change_partner_issuance_reservations')->where('partner_api_client_id', $client->getKey())->count())->toBe(1);
});

it('rejects invalid reservation identifiers and amounts', function (): void {
    $client = fundedOrderClient(['funded-orders:create']);
    $reservations = app(PartnerIssuanceReservationService::class);
    expect(fn () => $reservations->reserve($client, 'valid', '', [], 100, 'PHP'))
        ->toThrow(ValidationException::class);
    expect(fn () => $reservations->reserve($client, 'valid', 'key', [], -1, 'PHP'))
        ->toThrow(ValidationException::class);
    expect(fn () => $reservations->reserve($client, 'valid', 'key', [], 100, 'php'))
        ->toThrow(ValidationException::class);
});

it('enforces the full mandate and checks the reserved principal against the instructions', function (): void {
    $client = fundedOrderClient(['funded-orders:create']);
    $mandate = $client->mandate;
    $mandate['maximum_amount_minor'] = 5000;
    $client->forceFill(['mandate' => $mandate])->save();
    $reservations = app(PartnerIssuanceReservationService::class);
    $instructions = fundedReservationInstructions(50);
    expect(fn () => $reservations->reserve($client, 'wrong-amount', 'wrong-amount', $instructions, 100, 'PHP'))
        ->toThrow(ValidationException::class);
    $instructions['voucher_type'] = 'payable';
    $instructions['target_amount'] = 51;
    expect(fn () => $reservations->reserve($client, 'excess-target', 'excess-target', $instructions, 5000, 'PHP'))
        ->toThrow(ValidationException::class);
});

it('atomically completes the matching reservation and does not duplicate its reference on replay', function (): void {
    $client = fundedOrderClient(['funded-orders:create']);
    $reservations = app(PartnerIssuanceReservationService::class);
    $instructions = fundedReservationInstructions(50);
    data_set($instructions, 'metadata.custom.external_reference', 'complete-1');
    $instructions['_meta'] = [];
    $reservation = $reservations->reserve($client, 'complete-1', 'complete-key', $instructions, 5000, 'PHP');
    $order = fundedOrderForClient($client);
    data_set($instructions, '_meta.partner_issuance_reservation_id', $reservation['id']);
    $order->forceFill([
        'issuer_type' => $client->issuer::class, 'issuer_id' => (string) $client->issuer->getKey(),
        'instructions_ciphertext' => $instructions,
    ])->saveQuietly();
    $voucher = new Voucher(['code' => 'DONE', 'state' => 'active', 'metadata' => ['instructions' => $instructions]]);
    $voucher->owner()->associate($client->issuer);
    $voucher->save();
    $result = new GeneratePayCodeResultData(
        voucher_id: $voucher->getKey(), code: 'DONE', amount: 50.0, currency: 'PHP',
        issuer: new IssuerData(id: $client->issuer->getKey()),
        cost: new PricingEstimateData(currency: 'PHP', base_fee: 1, total: 1),
        wallet: [], debit: new DebitData(id: 1, amount: 51),
        links: new PayCodeLinksData(redeem: '/x/claim/DONE', redeem_path: '/x/claim/DONE', pay: '/x/pay/DONE', pay_path: '/x/pay/DONE'),
    );

    $altered = $instructions;
    data_set($altered, 'cash.amount', 49);
    $order->forceFill(['instructions_ciphertext' => $altered])->saveQuietly();
    expect(fn () => $reservations->bindToOrder($reservation['id'], $order->refresh()))->toThrow(RuntimeException::class);
    expect(fn () => $reservations->complete($reservation['id'], $order->refresh(), $result))->toThrow(RuntimeException::class);
    expect(PartnerApiPayCodeReference::query()->count())->toBe(0);
    $order->forceFill(['instructions_ciphertext' => $instructions])->saveQuietly();
    $reservations->bindToOrder($reservation['id'], $order->refresh());
    $reservations->bindToOrder($reservation['id'], $order->refresh());
    expect(DB::table('x_change_partner_issuance_reservations')->where('id', $reservation['id'])->value('funding_order_id'))->toBe($order->getKey());
    $reservations->complete($reservation['id'], $order->refresh(), $result);
    $reservations->complete($reservation['id'], $order->refresh(), $result);
    expect(PartnerApiPayCodeReference::query()->where('external_reference', 'complete-1')->count())->toBe(1)
        ->and(PartnerApiOperation::query()->where('operation', 'pay_code_issued')->count())->toBe(1)
        ->and(DB::table('x_change_partner_issuance_reservations')->where('id', $reservation['id'])->value('status'))->toBe('completed');
});

it('creates and replays an account-backed funding order with a bound reservation', function (): void {
    Queue::fake();
    Http::preventStrayRequests();
    enableNetbankTreasuryForTests();
    config()->set('x-change.funding.providers.netbank.enabled', true);
    $client = fundedOrderClient(['funded-orders:create']);
    fundTestUserWallet($client->issuer);
    $adapter = new FakeFundingProviderAdapter;
    $this->app->instance(FakeFundingProviderAdapter::class, $adapter);
    $this->app->tag(FakeFundingProviderAdapter::class, 'emi.funding-provider-adapters');
    $this->app->forgetInstance(FundingProviderAdapterRegistry::class);
    $balances = Mockery::mock(BuildBalanceOverview::class);
    $balances->shouldReceive('handle')->andReturn(['balances' => [['key' => 'local_ledger', 'balance_minor' => 0]]]);
    $this->app->instance(BuildBalanceOverview::class, $balances);
    $estimate = Mockery::mock(EstimatePayCodeCost::class);
    $estimate->shouldReceive('handle')->once()->andReturn(new PricingEstimateData(currency: 'PHP', pay_code_value: 50, account_debit: 50));
    $this->app->instance(EstimatePayCodeCost::class, $estimate);
    $action = app(CreatePartnerAccountFundedOrder::class);
    $instructions = fundedReservationInstructions(50);

    $first = $action->handle($client, 'account-order-1', 'account-key-1', $instructions, 'qr_ph');
    $replay = $action->handle($client, 'account-order-1', 'account-key-1', $instructions, 'qr_ph');
    expect($first['replayed'])->toBeFalse()
        ->and($replay['replayed'])->toBeTrue()
        ->and($replay['order']->getKey())->toBe($first['order']->getKey())
        ->and($first['order']->issuer_id)->toBe((string) $client->issuer->getKey())
        ->and(DB::table('x_change_partner_issuance_reservations')->where('external_reference', 'account-order-1')->value('funding_order_id'))->toBe($first['order']->getKey());
    expect(fn () => $action->handle($client, 'account-order-1', 'account-key-1', fundedReservationInstructions(51), 'qr_ph'))
        ->toThrow(IdempotencyConflict::class);
    expect(fn () => $action->handle($client, 'account-order-1', 'account-key-1', $instructions, 'bank_transfer', '09171234567'))
        ->toThrow(IdempotencyConflict::class);
});

it('issues an account-backed funded voucher and isolates its status', function (string $voucherType): void {
    Queue::fake();
    Http::preventStrayRequests();
    config()->set('x-change.commercial.legal_trace.legal_entity_reference', 'legal-entity:x-change:test');
    config()->set('x-change.commercial.legal_trace.profile_version', 'test-v1');
    app(ProvisionCommercialBaselines::class)->provision('commissioning-manifest:partner-funded-test');
    $snapshot = app(BootstrapCommercialOfferingFactory::class)->make('pay_code');
    $offering = CommercialOffering::query()->where('profile', 'pay_code')->sole();
    expect($offering->snapshot_hash)->toBe($snapshot->snapshotHash())
        ->and(data_get($offering->snapshot, 'effective_at'))->toBe('1970-01-01T00:00:00+00:00');
    config()->set('x-change.partner_api.account_funded_orders.enabled', true);
    config()->set('x-change.issuance_funding.on_demand.enabled', true);
    config()->set('x-change.issuance_funding.on_demand.fixed_qr_ph.enabled', true);
    config()->set('x-change.issuance_funding.on_demand.fixed_qr_ph.netbank_mode', 'registered_vca');
    config()->set('x-change.funding.providers.netbank.enabled', true);
    enableNetbankTreasuryForTests();
    $client = fundedOrderClient(['funded-orders:create', 'pay-codes:issue', 'funded-orders:read']);
    fundTestUserWallet($client->issuer);
    $adapter = new FakeFundingProviderAdapter;
    $this->app->instance(FakeFundingProviderAdapter::class, $adapter);
    $this->app->tag(FakeFundingProviderAdapter::class, 'emi.funding-provider-adapters');
    $this->app->forgetInstance(FundingProviderAdapterRegistry::class);
    $balances = Mockery::mock(BuildBalanceOverview::class);
    $balances->shouldReceive('handle')->andReturn(['balances' => [['key' => 'local_ledger', 'balance_minor' => 0]]]);
    $this->app->instance(BuildBalanceOverview::class, $balances);
    $estimate = Mockery::mock(EstimatePayCodeCost::class);
    $estimate->shouldReceive('handle')->andReturn(new PricingEstimateData(currency: 'PHP', pay_code_value: 50, account_debit: 50));
    $this->app->instance(EstimatePayCodeCost::class, $estimate);
    $spec = json_decode(file_get_contents(dirname(__DIR__, 3).'/resources/api/x-change-partner-api.openapi.json'), true, flags: JSON_THROW_ON_ERROR);
    $payload = data_get($spec, 'paths./account-funded-orders.post.requestBody.content.application/json.examples.'.$voucherType.'.value');
    $payload['external_reference'] = 'account-http-1';
    $payload['funding_method'] = 'qr_ph';
    $headers = ['Idempotency-Key' => 'account-http-key'];
    $first = $this->postJson('/api/partner/v1/account-funded-orders', $payload, $headers)->assertCreated()
        ->assertJsonPath('data.phase', 'payment_pending')->assertJsonPath('data.external_reference', 'account-http-1');
    $reference = $first->json('data.reference');
    $this->postJson('/api/partner/v1/account-funded-orders', $payload, $headers)->assertOk()
        ->assertJsonPath('data.reference', $reference)->assertJsonPath('meta.idempotency.replayed', true);
    $this->getJson('/api/partner/v1/funded-orders/'.$reference)->assertOk();
    $order = PayCodeIssuanceFundingOrder::query()->where('reference', $reference)->sole();
    $intent = $order->fundingIntent;
    $adapter->fundingObservation = new ProviderFundingObservationData(
        provider: 'netbank', providerTransactionId: 'PARTNER-HTTP-TEST',
        grossAmountMinor: $intent->expected_amount_minor, feeAmountMinor: 0,
        netAmountMinor: $intent->expected_amount_minor, currency: 'PHP', providerStatus: 'settled',
        verificationSource: 'test', payloadHash: hash('sha256', 'partner-http-test'),
        fundingAddress: 'sha256:'.hash('sha256', (string) $intent->funding_address_ciphertext),
        providerAccountReference: 'sha256:'.hash('sha256', (string) $intent->funding_address_ciphertext),
        occurredAt: now()->toDateTimeImmutable(), settledAt: now()->toDateTimeImmutable(),
        metadata: ['destination_verified' => true],
    );
    app(VerifyFundingIntent::class)->handle($intent, new FundingIntentVerificationData(
        trigger: FundingVerificationTrigger::Operator, actorId: (string) $client->issuer_id,
    ));
    app(SettleVerifiedFundingIntent::class)->handle($intent->refresh());
    $this->getJson('/api/partner/v1/funded-orders/'.$reference)->assertOk()
        ->assertJsonPath('data.phase', 'payment_verified');
    expect($order->refresh()->fundingIntent->settlement)->not->toBeNull();
    $providerBalance = Mockery::mock(AccountBalanceReadModelContract::class);
    $providerBalance->shouldReceive('providerBalanceMinor')->andReturn(5000);
    $this->app->instance(AccountBalanceReadModelContract::class, $providerBalance);
    $job = new ResumeOnDemandPayCodeIssuanceJob($order->getKey());
    $job->failed(new RuntimeException('Simulated worker failure'));
    $this->getJson('/api/partner/v1/funded-orders/'.$reference)->assertOk()->assertJsonPath('data.phase', 'review_required');
    app(RequestOnDemandPayCodeIssuanceRetry::class)->handle($order->refresh(), $client->issuer::class, (string) $client->issuer_id);
    $this->app->call([$job, 'handle']);
    $this->app->call([$job, 'handle']);
    $voucher = $order->refresh()->voucher;
    expect($voucher)->not->toBeNull();
    expect((float) data_get($voucher->metadata, 'instructions.cash.amount'))->toBe($voucherType === 'disbursable' ? 50.0 : 0.0)
        ->and((string) $voucher->owner_id)->toBe((string) $client->issuer_id)
        ->and(data_get($voucher->metadata, 'instructions.metadata.custom.external_reference'))->toBe('account-http-1');
    $this->getJson('/api/partner/v1/funded-orders/'.$reference)->assertOk()
        ->assertJsonPath('data.phase', 'issued')->assertJsonPath('data.pay_code.code', $voucher->code);
    expect(PartnerApiPayCodeReference::query()->where('external_reference', 'account-http-1')->count())->toBe(1);
    data_set($payload, $voucherType === 'disbursable' ? 'cash.amount' : 'target_amount', $voucherType === 'disbursable' ? 51 : 101);
    $this->postJson('/api/partner/v1/account-funded-orders', $payload, $headers)->assertConflict();
    fundedOrderClient(['funded-orders:read']);
    $this->getJson('/api/partner/v1/funded-orders/'.$reference)->assertNotFound();
})->with(['disbursable', 'payable', 'settlement']);

it('requires both scopes and keeps account-backed order creation disabled by default', function (): void {
    fundedOrderClient(['funded-orders:create']);
    $this->postJson('/api/partner/v1/account-funded-orders', [])->assertForbidden();
    fundedOrderClient(['funded-orders:create', 'pay-codes:issue']);
    $payload = validVoucherInstructions(50, 'INSTAPAY')->toArray();
    unset($payload['slice_plan']);
    data_set($payload, 'cash.validation.mobile', '09171234567');
    $payload['external_reference'] = 'disabled-test';
    $payload['funding_method'] = 'qr_ph';
    $this->postJson('/api/partner/v1/account-funded-orders', $payload, ['Idempotency-Key' => 'disabled-test'])->assertNotFound();
});

it('keeps paid order status readable to its client when new orders are paused', function (): void {
    config()->set('x-change.partner_api.funded_orders.enabled', false);
    $client = fundedOrderClient(['funded-orders:read']);
    $order = fundedOrderForClient($client);

    $this->getJson(route('x-change.partner-api.funded-orders.show', $order->reference))
        ->assertOk()
        ->assertJsonPath('data.reference', $order->reference)
        ->assertJsonPath('data.phase', 'payment_pending')
        ->assertJsonPath('data.amount_minor', 5000)
        ->assertJsonPath('data.pay_code', null);
});

it('reports payment verification and issuance phases without implying a Pay Code exists', function (): void {
    $client = fundedOrderClient(['funded-orders:read']);
    $order = fundedOrderForClient($client);

    foreach ([
        'funded' => 'payment_verified',
        'issuing' => 'issuance_pending',
        'issuance_attention' => 'review_required',
        'issued' => 'review_required',
        'cancelled' => 'cancelled',
        'expired' => 'expired',
    ] as $status => $phase) {
        $order->forceFill(['status' => $status])->saveQuietly();
        $this->getJson(route('x-change.partner-api.funded-orders.show', $order->reference))
            ->assertOk()
            ->assertJsonPath('data.phase', $phase)
            ->assertJsonPath('data.pay_code', null);
    }

    $order->forceFill(['status' => 'issued'])->saveQuietly();
    $this->getJson(route('x-change.partner-api.funded-orders.show', $order->reference))
        ->assertJsonPath('data.needs_review', true);
    $order->forceFill(['status' => 'funded', 'on_demand_amount_minor' => 0])->saveQuietly();
    $this->getJson(route('x-change.partner-api.funded-orders.show', $order->reference))
        ->assertJsonPath('data.phase', 'funded_from_client_funds');
});

it('does not expose another clients order', function (): void {
    $owner = fundedOrderClient(['funded-orders:read']);
    $order = fundedOrderForClient($owner);
    fundedOrderClient(['funded-orders:read']);

    $this->getJson(route('x-change.partner-api.funded-orders.show', $order->reference))
        ->assertNotFound();
});

it('requires the funded order scopes and creation switch', function (): void {
    fundedOrderClient(['capabilities:read']);

    $this->postJson(route('x-change.partner-api.funded-orders.store'), [])
        ->assertForbidden();

    fundedOrderClient(['funded-orders:create']);
    $this->postJson(route('x-change.partner-api.funded-orders.store'), [])
        ->assertNotFound();
});

it('creates and replays a client-bound bank-transfer checkout', function (): void {
    config()->set('x-change.partner_api.funded_orders.enabled', true);
    config()->set('x-change.public_auto_generate.enabled', true);
    config()->set('x-change.issuance_funding.on_demand.enabled', true);
    config()->set('x-change.funding.requests.bank_transfer.enabled', true);
    config()->set('x-change.funding.providers.netbank.enabled', true);
    config()->set('x-change.commercial.principal.reference', 'funded-agent-principal');
    Queue::fake();
    enableNetbankTreasuryForTests();
    $client = fundedOrderClient(['funded-orders:create', 'funded-orders:read']);
    CommercialPrincipal::query()->create([
        'reference' => 'funded-agent-principal',
        'legal_name' => 'Funded Agent Principal',
        'authorization_reference' => 'commissioning:funded-agent-principal:v1',
        'active' => true,
        'metadata' => ['interactive_login' => false],
    ]);
    $adapter = new FakeFundingProviderAdapter;
    $this->app->instance(FakeFundingProviderAdapter::class, $adapter);
    $this->app->tag(FakeFundingProviderAdapter::class, 'emi.funding-provider-adapters');
    $this->app->forgetInstance(FundingProviderAdapterRegistry::class);
    $estimate = Mockery::mock(EstimatePayCodeCost::class);
    $estimate->shouldReceive('handle')->twice()->andReturn(new PricingEstimateData(
        currency: 'PHP',
        pay_code_value: 50,
        account_debit: 50,
    ));
    $this->app->instance(EstimatePayCodeCost::class, $estimate);
    $payload = validVoucherInstructions(50, 'INSTAPAY')->toArray();
    unset($payload['slice_plan']);
    $payload['feedback']['webhook'] = null;
    $payload['funding_method'] = 'bank_transfer';
    $payload['payer_mobile'] = '09175180722';
    $headers = ['Idempotency-Key' => 'funded-order-test-1'];

    $invalid = $payload;
    $invalid['payer_mobile'] = 'not-a-mobile';
    $this->withHeaders($headers)
        ->postJson(route('x-change.partner-api.funded-orders.store'), $invalid)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('payer_mobile');

    $invalid = $payload;
    $invalid['cash']['amount'] = 50.001;
    $this->withHeaders($headers)
        ->postJson(route('x-change.partner-api.funded-orders.store'), $invalid)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('cash.amount');

    $first = $this->withHeaders($headers)
        ->postJson(route('x-change.partner-api.funded-orders.store'), $payload)
        ->assertCreated()
        ->assertJsonPath('data.funding_method', 'bank_transfer');
    $reference = $first->json('data.reference');

    $this->withHeaders($headers)
        ->postJson(route('x-change.partner-api.funded-orders.store'), $payload)
        ->assertOk()
        ->assertJsonPath('data.reference', $reference)
        ->assertJsonPath('meta.idempotency.replayed', true);

    $changed = $payload;
    $changed['cash']['amount'] = 51;
    $this->withHeaders($headers)
        ->postJson(route('x-change.partner-api.funded-orders.store'), $changed)
        ->assertConflict();

    config()->set('x-change.partner_api.funded_orders.maximum_open_per_client', 1);
    $this->withHeaders(['Idempotency-Key' => 'funded-order-test-2'])
        ->postJson(route('x-change.partner-api.funded-orders.store'), $payload)
        ->assertTooManyRequests();

    $this->getJson(route('x-change.partner-api.funded-orders.show', $reference))
        ->assertOk()
        ->assertJsonPath('data.reference', $reference);

    config()->set('x-change.partner_api.funded_orders.maximum_open_per_client', 2);
    config()->set('x-change.issuance_funding.on_demand.fixed_qr_ph.enabled', true);
    config()->set('x-change.issuance_funding.on_demand.fixed_qr_ph.netbank_mode', 'registered_vca');
    $qrPayload = $payload;
    $qrPayload['funding_method'] = 'qr_ph';
    unset($qrPayload['payer_mobile']);
    $qr = $this->withHeaders(['Idempotency-Key' => 'funded-order-test-qr'])
        ->postJson(route('x-change.partner-api.funded-orders.store'), $qrPayload)
        ->assertCreated()
        ->assertJsonPath('data.funding_method', 'qr_ph');
    expect($qr->json('data.funding.qr_code'))->toStartWith('data:image/png;base64,');

    expect(Checkout::query()->where('partner_api_client_id', $client->getKey())->count())->toBe(2)
        ->and(Checkout::query()->where('selected_method', 'bank_transfer')->sole()->visitor_mobile_ciphertext)->toBe('639175180722');

    PayCodeIssuanceFundingOrder::query()->where('reference', $reference)->firstOrFail()
        ->forceFill(['status' => 'funded', 'funded_at' => now()])->saveQuietly();
    $this->getJson(route('x-change.partner-api.funded-orders.show', $reference))
        ->assertOk()
        ->assertJsonPath('data.payment_required', false)
        ->assertJsonPath('data.funding', null);
});
