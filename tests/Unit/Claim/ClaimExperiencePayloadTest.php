<?php

declare(strict_types=1);

use LBHurtado\XChange\Support\Claim\ClaimExperiencePayload;

it('reads claim experience from supported state locations', function () {
    $experience = ['version' => 1];

    expect(ClaimExperiencePayload::fromState([
        'claim_experience' => $experience,
    ]))->toBe($experience)
        ->and(ClaimExperiencePayload::fromState([
            'metadata' => [
                'claim_experience' => $experience,
            ],
        ]))->toBe($experience)
        ->and(ClaimExperiencePayload::fromState([
            'instructions' => [
                'metadata' => [
                    'claim_experience' => $experience,
                ],
            ],
        ]))->toBe($experience);

});

it('writes claim experience into form flow instruction metadata', function () {
    $instructions = ClaimExperiencePayload::putIntoInstructions([
        'metadata' => [
            'voucher_code' => 'TEST123',
        ],
    ], [
        'version' => 1,
    ]);

    expect(data_get($instructions, 'metadata.voucher_code'))->toBe('TEST123')
        ->and(data_get($instructions, 'metadata.claim_experience.version'))->toBe(1);
});
