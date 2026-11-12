<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Server\McpServiceProvider;
use LBHurtado\XChange\Actions\PartnerApi\CreatePartnerApiClient;
use LBHurtado\XChange\Models\PartnerApiPayCodeReference;
use LBHurtado\XChange\Services\Commercial\ProvisionCommercialBaselines;
use LBHurtado\XChange\Tests\Fakes\User;
use LBHurtado\XMcp\XMcpServiceProvider;

it('issues through MCP and the real Partner API with simulated funds', function (): void {
    $mcpSource = getenv('XCHANGE_MCP_SOURCE');
    if (! is_string($mcpSource) || ! is_dir($mcpSource.'/vendor/laravel/mcp/src')) {
        $this->markTestSkipped('Set XCHANGE_MCP_SOURCE to a prepared x-mcp checkout for cross-package acceptance.');
    }
    $loader = require __DIR__.'/../../../vendor/autoload.php';
    $loader->addPsr4('Laravel\\Mcp\\', $mcpSource.'/vendor/laravel/mcp/src');
    $loader->addPsr4('LBHurtado\\XMcp\\', $mcpSource.'/src');
    config()->set('x-mcp.enabled', true);
    config()->set('x-mcp.api_base_url', 'https://partner-simulation.invalid/api/partner/v1');
    config()->set('x-mcp.expected_partner_contract_version', '1.6.0');
    config()->set('x-mcp.public_issuance.enabled', true);
    $this->app->register(McpServiceProvider::class);
    $this->app->register(XMcpServiceProvider::class);
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($key, $privateKey);
    config()->set('passport.private_key', $privateKey);
    config()->set('passport.public_key', openssl_pkey_get_details($key)['key']);
    config()->set('x-change.commercial.legal_trace.legal_entity_reference', 'legal-entity:x-change:test');
    config()->set('x-change.commercial.legal_trace.profile_version', 'test-v1');
    app(ProvisionCommercialBaselines::class)->provision('commissioning-manifest:mcp-simulation');
    $issuer = User::query()->create(['name' => 'Simulation', 'email' => 'mcp-'.str()->uuid().'@example.test', 'password' => Hash::make('test')]);
    fundTestUserWallet($issuer);
    $credential = app(CreatePartnerApiClient::class)->handle(name: 'MCP Simulation', issuer: $issuer, scopes: ['capabilities:read', 'pay-codes:estimate', 'pay-codes:issue', 'pay-codes:read', 'pay-codes:cancel']);
    $token = $this->postJson('/oauth/token', ['grant_type' => 'client_credentials', 'client_id' => $credential->client_id, 'client_secret' => $credential->client_secret, 'scope' => implode(' ', $credential->scopes)])->assertOk()->json('access_token');
    Http::preventStrayRequests();
    Http::fake(function (ClientRequest $request) {
        $originalRequest = app('request');
        $path = parse_url($request->url(), PHP_URL_PATH);
        $headers = ['Accept' => 'application/json', 'Authorization' => $request->header('Authorization')[0] ?? ''];
        foreach (['Idempotency-Key', 'X-Correlation-ID'] as $name) {
            if ($request->hasHeader($name)) {
                $headers[$name] = $request->header($name)[0];
            }
        }
        $response = $this->json($request->method(), $path, $request->data(), $headers);
        app()->instance('request', $originalRequest);

        return Http::response($response->getContent(), $response->getStatusCode(), ['Content-Type' => 'application/json']);
    });
    $payload = ['cash' => ['amount' => 100, 'currency' => 'PHP', 'validation' => ['mobile' => '09171234567']], 'inputs' => ['fields' => []], 'feedback' => ['mobile' => null], 'rider' => ['message' => null], 'count' => 1];
    $headers = ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json, text/event-stream'];
    $estimate = ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'estimate_pay_code', 'arguments' => ['pay_code' => $payload]]];
    $this->withHeaders($headers)->postJson('/mcp/x-change', $estimate)->assertOk()->assertJsonMissing(['isError' => true]);
    $rpc = ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'issue_pay_code', 'arguments' => ['pay_code' => $payload, 'external_reference' => 'mcp-simulation-001', 'confirm_issue' => true, 'idempotency_key' => 'mcp-simulation-001']]];
    $response = $this->withHeaders($headers)->postJson('/mcp/x-change', $rpc);
    $response->assertOk();
    expect($response->getContent())->toContain('mcp-simulation-001')->not->toContain('isError":true');
    $this->withHeaders($headers)->postJson('/mcp/x-change', $rpc)->assertOk()->assertJsonMissing(['isError' => true]);
    expect(PartnerApiPayCodeReference::query()->where('external_reference', 'mcp-simulation-001')->count())->toBe(1);
    $rpc['params']['arguments']['pay_code']['cash']['amount'] = 101;
    $changed = $this->withHeaders($headers)->postJson('/mcp/x-change', $rpc)->assertOk();
    expect($changed->getContent())->toContain('isError');
    $rpc['params']['arguments']['confirm_issue'] = false;
    $unconfirmed = $this->withHeaders($headers)->postJson('/mcp/x-change', $rpc)->assertOk();
    expect($unconfirmed->getContent())->toContain('isError');
    $public = $this->withHeaders(['Accept' => 'application/json, text/event-stream'])->postJson('/mcp/x-change/public', $rpc)->assertOk();
    expect($public->getContent())->toContain('error');
    expect(PartnerApiPayCodeReference::query()->where('external_reference', 'mcp-simulation-001')->count())->toBe(1);
});
