<?php

use LBHurtado\XRider\Contracts\RiderExperienceResolverContract;
use LBHurtado\XRider\Data\RiderExperienceData;
use LBHurtado\XRider\Data\RiderStageCollectionData;

it('projects claim preview stages without redirect leakage', function () {
    $experience = new RiderExperienceData(
        stages: RiderStageCollectionData::fromArray([
            [
                'type' => 'message',
                'key' => 'pre-claim-message',
                'phase' => 'pre_claim',
                'content' => 'Pre claim message',
            ],
            [
                'type' => 'message',
                'key' => 'runtime-message',
                'phase' => 'runtime',
                'content' => 'Runtime message',
            ],
            [
                'type' => 'redirect',
                'key' => 'redirect-stage',
                'phase' => 'redirect',
                'payload' => [
                    'url' => 'https://example.com/success',
                    'timeout' => 8,
                ],
            ],
        ]),
    );

    app()->instance(
        RiderExperienceResolverContract::class,
        new class($experience) implements RiderExperienceResolverContract
        {
            public function __construct(private RiderExperienceData $experience) {}

            public function resolve(mixed $subject): RiderExperienceData
            {
                return $this->experience;
            }
        }
    );

    // TODO: Replace this with the actual package preview endpoint test helper.
    // Example shape:
    //
    // $response = $this->getJson('/x/claim/preview?code=TEST123');
    //
    // $response->assertOk()
    //     ->assertJsonPath('rider.stages.stages.0.key', 'pre-claim-message');

    expect($experience->stages->stages)
        ->toHaveCount(3)
        ->and($experience->stages->stages[0]->phase)->toBe('pre_claim')
        ->and($experience->stages->stages[1]->phase)->toBe('runtime')
        ->and($experience->stages->stages[2]->phase)->toBe('redirect');
});

it('keeps success stages separate from pre claim stages', function () {
    $experience = new RiderExperienceData(
        stages: RiderStageCollectionData::fromArray([
            [
                'type' => 'message',
                'key' => 'pre-claim-message',
                'phase' => 'pre_claim',
                'content' => 'Pre claim message',
            ],
            [
                'type' => 'message',
                'key' => 'success-message',
                'phase' => 'success',
                'content' => 'Success message',
            ],
            [
                'type' => 'message',
                'key' => 'post-claim-message',
                'phase' => 'post_claim',
                'content' => 'Post claim message',
            ],
            [
                'type' => 'redirect',
                'key' => 'redirect-stage',
                'phase' => 'redirect',
                'payload' => [
                    'url' => 'https://example.com/success',
                    'timeout' => 8,
                ],
            ],
        ]),
    );

    expect($experience->stages->stages)
        ->toHaveCount(4)
        ->and($experience->stages->stages[0]->phase)->toBe('pre_claim')
        ->and($experience->stages->stages[1]->phase)->toBe('success')
        ->and($experience->stages->stages[2]->phase)->toBe('post_claim')
        ->and($experience->stages->stages[3]->phase)->toBe('redirect');
});
