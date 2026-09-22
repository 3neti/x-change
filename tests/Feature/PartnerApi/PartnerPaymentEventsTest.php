<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Payment\RecordVoucherCollection;
use LBHurtado\XChange\Data\Payment\VoucherPaymentResultData;
use LBHurtado\XChange\Models\PartnerApiClient;
use LBHurtado\XChange\Models\PartnerApiPayCodeReference;
use LBHurtado\XChange\Models\PartnerPaymentEvent;
use LBHurtado\XChange\Models\VoucherCollection;
use LBHurtado\XChange\Services\Payment\PartnerPaymentEventDelivery;
use LBHurtado\XChange\Services\Payment\PartnerPaymentEventOutbox;
use LBHurtado\XChange\Services\Payment\PartnerPaymentReceiver;
use LBHurtado\XChange\Tests\Fakes\User;

function partnerEventFixture(): VoucherCollection
{
    $user = User::query()->create(['name' => 'Fake issuer', 'email' => 'events@example.test', 'password' => 'fake']);
    $voucher = new Voucher(['code' => 'EVNT', 'state' => 'active', 'metadata' => []]);
    $voucher->owner()->associate($user);
    $voucher->save();
    $client = PartnerApiClient::query()->create(['reference' => 'partner-test', 'oauth_client_id' => 'fake-client', 'name' => 'Fake partner', 'issuer_type' => $user->getMorphClass(), 'issuer_id' => (string) $user->id, 'status' => 'active', 'activated_at' => now()]);
    PartnerApiPayCodeReference::query()->create(['partner_api_client_id' => $client->id, 'voucher_id' => $voucher->id, 'external_reference' => 'bpls-ps-test', 'terms_hash' => hash('sha256', 'test')]);

    return VoucherCollection::query()->create(['voucher_id' => $voucher->id, 'collection_number' => 1, 'status' => 'collected', 'requested_amount_minor' => 397500, 'collected_amount_minor' => 397500, 'currency' => 'PHP', 'provider' => 'fake', 'completed_at' => now(), 'payer_name' => 'DO NOT SEND', 'meta' => ['secret' => 'DO NOT SEND']]);
}

beforeEach(function (): void {
    Http::preventStrayRequests();
    config()->set('x-change.partner_api.payment_events', ['enabled' => true, 'allowed_hosts' => ['receiver.example.test'], 'receivers' => ['partner-test' => ['url' => 'https://receiver.example.test/integrations/x-change/payment-events', 'secret' => str_repeat('s', 32)]]]);
    app()->bind(PartnerPaymentReceiver::class, fn () => new class extends PartnerPaymentReceiver
    {
        protected function addresses(string $host): array
        {
            return ['93.184.216.34'];
        }
    });
});

it('captures one stable minimal event with exact partner binding and rolls back with its transaction', function (): void {
    $collection = partnerEventFixture();
    DB::beginTransaction();
    app(PartnerPaymentEventOutbox::class)->record($collection);
    DB::rollBack();
    expect(PartnerPaymentEvent::query()->count())->toBe(0);
    $event = app(PartnerPaymentEventOutbox::class)->record($collection);
    $again = app(PartnerPaymentEventOutbox::class)->record($collection);
    expect($again->event_id)->toBe($event->event_id)->and(PartnerPaymentEvent::query()->count())->toBe(1);
    $body = json_decode($event->body, true);
    expect($body)->toHaveCount(9)->toMatchArray(['type' => 'payment.collected.v1', 'partner_reference' => 'partner-test', 'pay_code' => 'EVNT', 'external_reference' => 'bpls-ps-test', 'amount_minor' => 397500, 'currency' => 'PHP']);
    expect($event->body)->not->toContain('DO NOT SEND');
    Http::assertNothingSent();
});

it('does not create events while disabled, unbound, failed or inactive', function (): void {
    $collection = partnerEventFixture();
    config()->set('x-change.partner_api.payment_events.enabled', false);
    expect(app(PartnerPaymentEventOutbox::class)->record($collection))->toBeNull();
    config()->set('x-change.partner_api.payment_events.enabled', true);
    $collection->status = 'failed';
    expect(app(PartnerPaymentEventOutbox::class)->record($collection))->toBeNull();
    $collection->status = 'collected';
    DB::table('x_change_partner_api_pay_code_references')->delete();
    expect(app(PartnerPaymentEventOutbox::class)->record($collection))->toBeNull();
});

it('retains the durable event when receiver configuration is missing', function (): void {
    config()->set('x-change.partner_api.payment_events.receivers', []);
    $event = app(PartnerPaymentEventOutbox::class)->record(partnerEventFixture());
    expect($event)->not->toBeNull();
    expect(app(PartnerPaymentEventDelivery::class)->deliver($event->id))->toBeFalse();
    expect($event->fresh()->status)->toBe('pending')->and($event->fresh()->body)->toBe($event->body);
    Http::assertNothingSent();
});

it('records the event inside the canonical collection transaction with no HTTP side effect', function (): void {
    $existing = partnerEventFixture();
    DB::beginTransaction();
    $collection = app(RecordVoucherCollection::class)->handle(
        $existing->voucher,
        new VoucherPaymentResultData(voucher_code: 'EVNT', status: 'collected', amount: 10, provider: 'fake', provider_transaction_id: 'fake-transaction'),
        ['idempotency_key' => 'fake-collection'],
    );
    expect(PartnerPaymentEvent::query()->where('collection_id', $collection->id)->count())->toBe(1);
    DB::rollBack();
    expect(PartnerPaymentEvent::query()->count())->toBe(0)->and(VoucherCollection::query()->count())->toBe(1);
    Http::assertNothingSent();
});

it('does not follow redirects and keeps response bodies out of diagnostics', function (): void {
    $event = app(PartnerPaymentEventOutbox::class)->record(partnerEventFixture());
    Http::fake(['*' => Http::response('secret-body', 302, ['Location' => 'http://169.254.169.254/'])]);
    expect(app(PartnerPaymentEventDelivery::class)->deliver($event->id))->toBeFalse();
    expect($event->fresh()->last_http_status)->toBe(302)->and($event->fresh()->last_error)->toBe('receiver_http_error');
    Http::assertSentCount(1);
});

it('resigns the identical event on retry and accepts durable or duplicate acknowledgements', function (): void {
    $event = app(PartnerPaymentEventOutbox::class)->record(partnerEventFixture());
    Http::fakeSequence()->push('sensitive response', 503)->push('', 202);
    $delivery = app(PartnerPaymentEventDelivery::class);
    expect($delivery->deliver($event->id))->toBeFalse();
    expect($event->fresh()->last_error)->toBe('receiver_http_error');
    $this->travel(31)->seconds();
    expect($delivery->deliver($event->id))->toBeTrue();
    expect($event->fresh()->status)->toBe('delivered');
    Http::assertSentCount(2);
    $requests = Http::recorded();
    foreach ($requests as [$request]) {
        $timestamp = $request->header('X-XChange-Timestamp')[0];
        expect($request->body())->toBe($event->body);
        expect($request->header('X-XChange-Signature')[0])->toBe('sha256='.hash_hmac('sha256', $timestamp.'.'.$event->body, str_repeat('s', 32)));
    }
    expect($requests[0][0]->header('X-XChange-Timestamp'))->not->toBe($requests[1][0]->header('X-XChange-Timestamp'));
    expect($delivery->deliver($event->id))->toBeFalse();
    Http::assertSentCount(2);
});

it('recovers expired worker leases but not live leases and stops after bounded attempts', function (): void {
    $event = app(PartnerPaymentEventOutbox::class)->record(partnerEventFixture());
    $event->update(['status' => 'sending', 'lease_token' => (string) str()->uuid(), 'lease_expires_at' => now()->addMinutes(2)]);
    Http::fakeSequence()->push('', 500)->push('', 200);
    expect(app(PartnerPaymentEventDelivery::class)->deliver($event->id))->toBeFalse();
    Http::assertNothingSent();
    $this->travel(3)->minutes();
    $event->update(['attempts' => 7]);
    expect(app(PartnerPaymentEventDelivery::class)->deliver($event->id))->toBeFalse();
    expect($event->fresh()->status)->toBe('failed')->and($event->fresh()->attempts)->toBe(8);
    $this->artisan('x-change:partner-payment-events:deliver', ['--retry' => $event->event_id])->assertSuccessful();
    expect($event->fresh()->status)->toBe('delivered')->and($event->fresh()->event_id)->toBe($event->event_id);
});

it('blocks suspended partners and does not log exception messages', function (): void {
    $event = app(PartnerPaymentEventOutbox::class)->record(partnerEventFixture());
    DB::table('x_change_partner_api_clients')->where('id', $event->partner_api_client_id)->update(['status' => 'suspended']);
    expect(app(PartnerPaymentEventDelivery::class)->deliver($event->id))->toBeFalse();
    expect($event->fresh()->last_error)->toBe('partner_binding_inactive');
    Http::assertNothingSent();
});

it('rejects unsafe receiver URLs and private DNS answers before HTTP', function (string $url, array $addresses): void {
    config()->set('x-change.partner_api.payment_events.receivers.partner-test.url', $url);
    $receiver = new class($addresses) extends PartnerPaymentReceiver
    {
        public function __construct(private array $testAddresses) {}

        protected function addresses(string $host): array
        {
            return $this->testAddresses;
        }
    };
    expect(fn () => $receiver->resolve('partner-test'))->toThrow(RuntimeException::class);
    Http::assertNothingSent();
})->with([
    ['http://receiver.example.test/events', ['93.184.216.34']],
    ['https://evil.example.test/events', ['93.184.216.34']],
    ['https://user:pass@receiver.example.test/events', ['93.184.216.34']],
    ['https://receiver.example.test:8443/events', ['93.184.216.34']],
    ['https://receiver.example.test/events', ['127.0.0.1']],
    ['https://receiver.example.test/events', ['93.184.216.34', '169.254.169.254']],
    ['https://receiver.example.test/events', ['::1']],
    ['https://receiver.example.test/events', ['::ffff:127.0.0.1']],
    ['https://receiver.example.test/events', ['100.64.0.1']],
    ['https://receiver.example.test/events', ['224.0.0.1']],
]);
