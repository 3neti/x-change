<?php

use LBHurtado\XChange\Actions\Claim\ReadCompiledClaimSubmission;

it('reads compiled claim submission handoff from session', function () {
    session()->put('compiled_claim_submission', [
        'code' => ' test123 ',
        'inputs' => [
            'first_name' => 'Anaïs',
        ],
    ]);

    $submission = app(ReadCompiledClaimSubmission::class)->handle();

    expect($submission)->not->toBeNull()
        ->and($submission->code)->toBe('TEST123')
        ->and($submission->inputs)->toBe([
            'first_name' => 'Anaïs',
        ]);
});

it('returns null when compiled claim submission handoff is missing', function () {
    session()->forget('compiled_claim_submission');

    expect(app(ReadCompiledClaimSubmission::class)->handle())->toBeNull();
});

it('returns null when compiled claim submission handoff is malformed', function () {
    session()->put('compiled_claim_submission', [
        'code' => 'TEST123',
        'inputs' => 'not-an-array',
    ]);

    expect(app(ReadCompiledClaimSubmission::class)->handle())->toBeNull();
});
