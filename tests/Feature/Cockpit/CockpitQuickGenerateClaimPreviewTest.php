<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use LBHurtado\XChange\Contracts\CockpitReadModelProviderContract;
use LBHurtado\XChange\Data\Cockpit\CockpitReadModelQueryData;

it('exposes the claim preview contract on the quick generate read model', function (): void {
    expect(Route::has('x-change.cockpit.quick-generate.claim-previews.store'))->toBeTrue();

    $readModel = app(CockpitReadModelProviderContract::class)
        ->forQuickGenerate(new CockpitReadModelQueryData);

    expect($readModel->claim_preview_contract->schema)->toBe('x-change.cockpit.quick-generate-claim-preview.v1')
        ->and($readModel->claim_preview_contract->status)->toBe('registered')
        ->and($readModel->claim_preview_contract->route)->toBe('x-change.cockpit.quick-generate.claim-previews.store')
        ->and($readModel->claim_preview_contract->route_url)->toBe('/x/cockpit/quick-generate/claim-previews')
        ->and($readModel->claim_preview_contract->money_movement)->toBeFalse();
});

it('renders a dry-run claim preview from quick generate instructions', function (): void {
    actingAsTestUser();

    $response = $this->withHeaders([
        'Accept' => 'application/json',
    ])->post(route('x-change.cockpit.quick-generate.claim-previews.store'), [
        'cash' => [
            'amount' => 25,
            'currency' => 'PHP',
        ],
        'inputs' => [
            'fields' => [],
        ],
        'feedback' => [
            'mobile' => null,
        ],
        'rider' => [
            'message' => 'Issuer preview message.',
            'url' => 'https://example.test/rider',
            'splash' => '<section>Issuer preview splash</section>',
            'redirect_timeout' => 4,
            'og_source' => 'message',
        ],
        'dry_run' => true,
        'refresh_preview' => true,
    ]);

    $response
        ->assertSuccessful()
        ->assertJsonPath('schema', 'x-change.claim-experience-preview.result.v1')
        ->assertJsonPath('status', 'ready')
        ->assertJsonPath('source', 'cockpit.quick-generate')
        ->assertJsonPath('money_movement', false)
        ->assertJsonPath('provider_calls', false)
        ->assertJsonPath('cache_hit', false)
        ->assertJsonPath('artifacts.view_options.default.label', 'Default PDF');

    expect($response->json('artifacts.storyboard_pdf'))->toBeFile()
        ->and($response->json('artifacts.storyboard_html'))->toBeFile();
});
