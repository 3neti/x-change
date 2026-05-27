<?php

use LBHurtado\XChange\Tests\Support\Rider\RiderLifecyclePhasePolicy;
use LBHurtado\XRider\Data\RiderExperienceData;
use LBHurtado\XRider\Data\RiderStageCollectionData;
use LBHurtado\XRider\Data\RiderSubjectData;

function riderExperienceWithStages(array $stages): RiderExperienceData
{
    return new RiderExperienceData(
        state: 'accepted',
        subject: new RiderSubjectData(
            type: 'voucher',
            id: 'TEST123',
        ),
        stages: RiderStageCollectionData::fromArray($stages),
    );
}

function riderStageKeysForClaimPreview(RiderExperienceData $experience): array
{
    return RiderLifecyclePhasePolicy::keys(
        RiderLifecyclePhasePolicy::claimPreviewStages($experience->stages?->stages ?? [])
    );
}

function riderStageKeysForSuccess(RiderExperienceData $experience): array
{
    return RiderLifecyclePhasePolicy::keys(
        RiderLifecyclePhasePolicy::successStages($experience->stages?->stages ?? [])
    );
}

it('allows claim preview to project pre claim and runtime stages only', function () {
    $experience = riderExperienceWithStages([
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
            'type' => 'message',
            'key' => 'success-message',
            'phase' => 'success',
            'content' => 'Success message',
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
    ]);

    expect(riderStageKeysForClaimPreview($experience))
        ->toBe([
            'pre-claim-message',
            'runtime-message',
        ]);
});

it('allows success page to project success post claim and redirect stages only', function () {
    $experience = riderExperienceWithStages([
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
    ]);

    expect(riderStageKeysForSuccess($experience))
        ->toBe([
            'success-message',
            'post-claim-message',
            'redirect-stage',
        ]);
});

it('does not leak redirect stages into claim preview projection', function () {
    $experience = riderExperienceWithStages([
        [
            'type' => 'redirect',
            'key' => 'redirect-stage',
            'phase' => 'redirect',
            'payload' => [
                'url' => 'https://example.com/success',
            ],
        ],
    ]);

    expect(riderStageKeysForClaimPreview($experience))
        ->toBe([]);
});

it('does not leak pre claim stages into success projection', function () {
    $experience = riderExperienceWithStages([
        [
            'type' => 'message',
            'key' => 'pre-claim-message',
            'phase' => 'pre_claim',
            'content' => 'Pre claim message',
        ],
    ]);

    expect(riderStageKeysForSuccess($experience))
        ->toBe([]);
});
