<?php

use LBHurtado\XRider\Data\RiderExperienceData;
use LBHurtado\XRider\Data\RiderStageCollectionData;
use LBHurtado\XRider\Data\RiderStageData;
use LBHurtado\XRider\Data\RiderSubjectData;

function stagePhase(RiderStageData $stage): ?string
{
    return $stage->phase ?? $stage->payload['phase'] ?? null;
}

function stageKeysForPhases(RiderExperienceData $experience, array $allowedPhases): array
{
    return collect($experience->stages?->stages ?? [])
        ->filter(fn (RiderStageData $stage) => in_array(stagePhase($stage), $allowedPhases, true))
        ->map(fn (RiderStageData $stage) => $stage->key)
        ->values()
        ->all();
}

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

    expect(stageKeysForPhases($experience, ['pre_claim', 'runtime']))
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
    ]
    );

    expect(stageKeysForPhases($experience, ['success', 'post_claim', 'redirect']))
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
    ],
    );

    expect(stageKeysForPhases($experience, ['pre_claim', 'runtime']))
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
    ],
    );

    expect(stageKeysForPhases($experience, ['success', 'post_claim', 'redirect']))
        ->toBe([]);
});
