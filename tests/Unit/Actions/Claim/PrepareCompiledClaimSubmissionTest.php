<?php

use Illuminate\Http\Request;
use LBHurtado\XChange\Actions\Claim\PrepareCompiledClaimSubmission;

it('stores compiled claim submission handoff in session', function () {
    $submission = app(PrepareCompiledClaimSubmission::class)->handle([
        'code' => ' test123 ',
        'inputs' => [
            'first_name' => 'Lester',
        ],
    ]);

    expect($submission->code)->toBe('TEST123')
        ->and(session()->get('compiled_claim_submission'))->toBe([
            'code' => 'TEST123',
            'inputs' => [
                'first_name' => 'Lester',
            ],
        ]);
});
