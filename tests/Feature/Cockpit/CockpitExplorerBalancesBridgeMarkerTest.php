<?php

declare(strict_types=1);

use LBHurtado\XChange\Services\BuildBalanceOverview;

it('exposes a cockpit bridge marker on the legacy pay code index page', function () {
    actingAsTestUser();

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.pay-codes.index'))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/pay-codes/Index')
        ->assertJsonPath('props.cockpit_bridge.schema', 'x-change.pay-code-index.cockpit-bridge.v1')
        ->assertJsonPath('props.cockpit_bridge.status', 'available')
        ->assertJsonPath('props.cockpit_bridge.legacy_owner', 'PayCodeIndexPageController')
        ->assertJsonPath('props.cockpit_bridge.cockpit_route', '/x/cockpit/pay-codes')
        ->assertJsonPath('props.cockpit_bridge.mutation.legacy_page_remains_owner', true)
        ->assertJsonPath('props.cockpit_bridge.mutation.cockpit_replaces_legacy_page', false)
        ->assertJsonPath('props.cockpit_bridge.redactions.payloads', 'bridge-metadata-only')
        ->assertJsonMissingPath('props.cockpit_bridge.raw_payload');
});

it('exposes a cockpit bridge marker on the legacy balances page', function () {
    app()->instance(BuildBalanceOverview::class, new class extends BuildBalanceOverview
    {
        public function __construct() {}

        public function handle(mixed $owner, ?string $provider = null, bool $syncIfStale = true, bool $forceSync = false): array
        {
            return [
                'provider' => 'netbank',
                'topology' => 'ledger_pooled',
                'authority' => 'local_ledger',
                'sync_status' => 'not_required',
                'authoritative' => [
                    'key' => 'local_ledger',
                    'balance' => 10000,
                    'currency' => 'PHP',
                ],
            ];
        }
    });

    actingAsTestUser();

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.balances.index'))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/balances/Index')
        ->assertJsonPath('props.cockpit_bridge.schema', 'x-change.balances.cockpit-bridge.v1')
        ->assertJsonPath('props.cockpit_bridge.status', 'available')
        ->assertJsonPath('props.cockpit_bridge.legacy_owner', 'BalancePageController')
        ->assertJsonPath('props.cockpit_bridge.cockpit_route', '/x/cockpit')
        ->assertJsonPath('props.cockpit_bridge.mutation.legacy_page_remains_owner', true)
        ->assertJsonPath('props.cockpit_bridge.mutation.cockpit_replaces_legacy_page', false)
        ->assertJsonPath('props.cockpit_bridge.mutation.funding_mutation_enabled', false)
        ->assertJsonPath('props.cockpit_bridge.redactions.payloads', 'bridge-metadata-only')
        ->assertJsonMissingPath('props.cockpit_bridge.wallet');
});
