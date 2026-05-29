<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use LBHurtado\XChange\Http\Controllers\Web\Claim\ClaimSuccessPageController;
use LBHurtado\XChange\Support\Claim\ClaimExperiencePayload;
use LBHurtado\XRider\Contracts\RiderExperienceResolverContract;
use LBHurtado\XRider\Data\RiderExperienceData;
use LBHurtado\XRider\Data\RiderStageCollectionData;
use LBHurtado\XRider\Data\RiderSubjectData;
use LBHurtado\XRider\Enums\RiderOutcomeState;

beforeEach(function () {
    $viewsPath = __DIR__.'/../../Fixtures/views';

    if (! is_dir($viewsPath)) {
        mkdir($viewsPath, 0777, true);
    }

    file_put_contents($viewsPath.'/app.blade.php', <<<'BLADE'
<div id="app" data-page="{{ json_encode($page) }}"></div>
BLADE);

    app('view')->addLocation($viewsPath);

    config()->set('inertia.testing.ensure_pages_exist', false);

    app()->instance(
        RiderExperienceResolverContract::class,
        new class implements RiderExperienceResolverContract
        {
            public function resolve(RiderSubjectData $subject, array $context = []): RiderExperienceData
            {
                return new RiderExperienceData(
                    state: RiderOutcomeState::AcceptedSuccess,
                    subject: $subject,
                    stages: new RiderStageCollectionData(
                        stages: [],
                    ),
                );
            }
        }
    );

    Route::get('/x/claim/{code}/success', ClaimSuccessPageController::class)
        ->name('x-change.claim.success');
});

it('exposes claim experience redirect countdown metadata to the success page', function () {
    $this->withoutMiddleware();

    $voucher = issueVoucher(validVoucherInstructions(
        overrides: [
            'rider' => [
                'message' => 'SUCCESS DEMO: Thank you for claiming.',
                'url' => 'https://example.com/after-claim',
            ],
        ],
    ));

    $response = $this->getJson(route('x-change.claim.success', [
        'code' => $voucher->code,
    ], false).'?'.http_build_query([
        'state' => [
            'status' => 'completed',
        ],
        'subject' => [
            'type' => 'voucher',
            'id' => $voucher->getKey(),
            'code' => $voucher->code,
        ],
    ]))
        ->assertOk()
        ->assertJsonPath('claim_experience.options.show_redirect_countdown', true)
        ->assertJsonPath('claim_experience.diagnostics.redirect_owner', 'claim-widget')
        ->assertJsonPath('redirect.show_countdown', true)
        ->assertJsonPath('redirect.owner', 'claim-widget')
        ->assertJsonPath('redirect.delay_seconds', 5);

    $claimExperience = $response->json('claim_experience');

    expect(ClaimExperiencePayload::isClaimWidgetRedirect($claimExperience))->toBeTrue();
});
