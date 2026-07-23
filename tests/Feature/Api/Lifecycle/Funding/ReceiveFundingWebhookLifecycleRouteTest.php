<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use LBHurtado\EmiCore\Data\Funding\WebhookAuthenticationData;
use LBHurtado\EmiCore\Models\WebhookReceipt;
use LBHurtado\XChange\Jobs\Funding\VerifyFundingWebhookReceiptJob;
use LBHurtado\XChange\Services\Funding\FundingProviderAdapterRegistry;
use LBHurtado\XChange\Tests\Fakes\FakeFundingProviderAdapter;

beforeEach(function () {
    config()->set('x-change.funding.webhook_middleware', []);
    Queue::fake();
    $this->fundingAdapter = new FakeFundingProviderAdapter;
    $this->app->instance(FakeFundingProviderAdapter::class, $this->fundingAdapter);
    $this->app->tag(FakeFundingProviderAdapter::class, 'emi.funding-provider-adapters');
    $this->app->forgetInstance(FundingProviderAdapterRegistry::class);
});

it('stores authenticated raw evidence without changing a balance', function () {
    $body = 'opaque pristine provider body';

    $response = fundingWebhookRequest($this, 'netbank', $body);

    $response->assertAccepted()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.acknowledgement', 'accepted')
        ->assertJsonPath('data.provider', 'netbank')
        ->assertJsonPath('meta.balance_changed', false)
        ->assertJsonPath('meta.verification_queued', true);

    Queue::assertPushed(
        VerifyFundingWebhookReceiptJob::class,
        fn (VerifyFundingWebhookReceiptJob $job): bool => $job->webhookReceiptId === WebhookReceipt::query()->sole()->getKey(),
    );

    $receipt = WebhookReceipt::query()->sole();
    $raw = DB::table('webhook_receipts')->find($receipt->getKey());

    expect($receipt->provider_code)->toBe('netbank')
        ->and($receipt->event_type)->toBe('fake.funding')
        ->and($receipt->signature_verified)->toBeTrue()
        ->and($receipt->authentication_status)->toBe('authenticated')
        ->and($receipt->processing_status)->toBe('received')
        ->and($receipt->body_sha256)->toBe(hash('sha256', $body))
        ->and($receipt->raw_body)->toBe($body)
        ->and($receipt->headers)->toHaveKey('content-type')
        ->and($raw->raw_body)->not->toContain($body)
        ->and($raw->headers)->not->toContain('text/plain');
});

it('deduplicates replayed provider evidence by its raw body', function () {
    fundingWebhookRequest($this, 'netbank', 'same opaque provider body')->assertAccepted();
    fundingWebhookRequest($this, 'netbank', 'same opaque provider body')->assertAccepted();

    expect(WebhookReceipt::query()->count())->toBe(1);
});

it('preserves rejected evidence but never accepts it for verification', function () {
    $this->fundingAdapter->webhookAuthentication = new WebhookAuthenticationData(
        authenticated: false,
        method: 'source-ip-allowlist',
        reason: 'source-ip-not-allowed',
    );

    fundingWebhookRequest($this, 'netbank', 'rejected opaque body')
        ->assertUnauthorized()
        ->assertJsonPath('code', 'FUNDING_WEBHOOK_AUTHENTICATION_FAILED');

    $receipt = WebhookReceipt::query()->sole();

    expect($receipt->signature_verified)->toBeFalse()
        ->and($receipt->authentication_status)->toBe('rejected')
        ->and($receipt->processing_status)->toBe('rejected')
        ->and($receipt->event_type)->toBeNull();
});

it('does not upgrade previously rejected evidence when the same body is replayed', function () {
    $this->fundingAdapter->webhookAuthentication = new WebhookAuthenticationData(
        authenticated: false,
        method: 'source-ip-allowlist',
        reason: 'source-ip-not-allowed',
    );
    fundingWebhookRequest($this, 'netbank', 'replayed rejected body')->assertUnauthorized();

    $this->fundingAdapter->webhookAuthentication = new WebhookAuthenticationData(
        authenticated: true,
        method: 'source-ip-allowlist',
    );
    fundingWebhookRequest($this, 'netbank', 'replayed rejected body')->assertUnauthorized();

    expect(WebhookReceipt::query()->count())->toBe(1)
        ->and(WebhookReceipt::query()->sole()->authentication_status)->toBe('rejected');
});

it('rejects oversized evidence before persistence', function () {
    config()->set('x-change.funding.webhook_max_body_bytes', 4);

    fundingWebhookRequest($this, 'netbank', '12345')
        ->assertStatus(413)
        ->assertJsonPath('code', 'FUNDING_WEBHOOK_PAYLOAD_TOO_LARGE');

    expect(WebhookReceipt::query()->count())->toBe(0);
});

it('does not accept an unregistered funding provider', function () {
    $this->app->instance(
        FundingProviderAdapterRegistry::class,
        new FundingProviderAdapterRegistry([]),
    );

    fundingWebhookRequest($this, 'unknown', 'opaque')
        ->assertServiceUnavailable()
        ->assertJsonPath('code', 'FUNDING_PROVIDER_UNAVAILABLE');

    expect(WebhookReceipt::query()->count())->toBe(0);
});

function fundingWebhookRequest(
    object $test,
    string $provider,
    string $body,
): TestResponse {
    return $test->call(
        method: 'POST',
        uri: '/api/x/v1/funding/webhooks/'.$provider,
        server: [
            'CONTENT_TYPE' => 'text/plain;charset=ISO-8859-1',
            'REMOTE_ADDR' => '52.74.254.158',
        ],
        content: $body,
    );
}
