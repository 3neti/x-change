<?php

use LBHurtado\XChange\Data\CompiledClaimSubmissionData;

it('normalizes compiled claim submission code', function () {
    $data = CompiledClaimSubmissionData::fromValidated([
        'code' => ' test123 ',
        'inputs' => [
            'first_name' => 'Lester',
        ],
    ]);

    expect($data->code)->toBe('TEST123')
        ->and($data->inputs)->toBe([
            'first_name' => 'Lester',
        ])
        ->and($data->toSessionPayload())->toBe([
            'code' => 'TEST123',
            'inputs' => [
                'first_name' => 'Lester',
            ],
        ]);
});
