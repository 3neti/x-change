<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use LBHurtado\XChange\Http\Controllers\Web\Claim\ClaimRedirectController;
use LBHurtado\XRider\Contracts\RiderAnalyticsRecorderContract;
use LBHurtado\XRider\Contracts\RiderExperienceResolverContract;
use LBHurtado\XRider\Contracts\SuccessRedirectResolverContract;
use LBHurtado\XRider\Data\RiderAnalyticsEventData;
use LBHurtado\XRider\Data\RiderExperienceData;
use LBHurtado\XRider\Data\RiderRedirectData;
use LBHurtado\XRider\Data\RiderStageCollectionData;
use LBHurtado\XRider\Data\RiderSubjectData;
use LBHurtado\XRider\Enums\RiderOutcomeState;

beforeEach(function () {
    $this->app->instance(
        RiderExperienceResolverContract::class,
        new class implements RiderExperienceResolverContract
        {
            public function resolve(RiderSubjectData $subject, array $context = []): RiderExperienceData
            {
                $url = data_get($context, 'rider.url');

                return new RiderExperienceData(
                    state: RiderOutcomeState::AcceptedSuccess,
                    subject: $subject,
                    redirect: filled($url)
                        ? new RiderRedirectData(
                            enabled: true,
                            url: $url,
                            timeout: 5,
                        )
                        : null,
                    stages: new RiderStageCollectionData(stages: []),
                );
            }
        }
    );

    $this->app->instance(
        SuccessRedirectResolverContract::class,
        new class implements SuccessRedirectResolverContract
        {
            public function resolve(RiderExperienceData $experience): string
            {
                return $experience->redirect?->url ?? '';
            }
        }
    );

    $this->app->instance(
        RiderAnalyticsRecorderContract::class,
        new class implements RiderAnalyticsRecorderContract
        {
            public function record(RiderAnalyticsEventData $event): void
            {
                //
            }
        }
    );

    Route::get('/x/claim/{code}/redirect', ClaimRedirectController::class)
        ->name('x-change.claim.redirect');
});

it('redirects to the voucher rider url through the redirect gate', function () {
    $this->withoutMiddleware();

    $voucher = issueVoucher(validVoucherInstructions(
        overrides: [
            'rider' => [
                'message' => 'SUCCESS DEMO: Thank you for claiming.',
                'url' => 'https://example.com/after-claim',
            ],
        ],
    ));

    $this->get(route('x-change.claim.redirect', [
        'code' => $voucher->code,
    ]))
        ->assertRedirect('https://example.com/after-claim');
});

it('returns not found when voucher does not exist', function () {
    $this->withoutMiddleware();

    $this->get(route('x-change.claim.redirect', [
        'code' => 'MISSING-CODE',
    ]))
        ->assertNotFound();
});

it('returns not found when voucher has no rider url', function () {
    $this->withoutMiddleware();

    $voucher = issueVoucher(validVoucherInstructions(
        overrides: [
            'rider' => [
                'message' => 'SUCCESS DEMO: Thank you for claiming.',
                'url' => null,
            ],
        ],
    ));

    $this->get(route('x-change.claim.redirect', [
        'code' => $voucher->code,
    ]))
        ->assertNotFound();
});
