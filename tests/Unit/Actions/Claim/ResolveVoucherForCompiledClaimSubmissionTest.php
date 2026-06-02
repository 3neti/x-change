<?php

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Claim\ResolveVoucherForCompiledClaimSubmission;
use LBHurtado\XChange\Data\CompiledClaimSubmissionData;

it('resolves voucher for compiled claim submission', function () {
    $voucher = Voucher::factory()->create([
        'code' => 'TEST123',
    ]);

    $submission = new CompiledClaimSubmissionData(
        code: 'TEST123',
        inputs: [
            'first_name' => 'Lester',
        ],
    );

    $resolved = app(ResolveVoucherForCompiledClaimSubmission::class)
        ->handle($submission);

    expect($resolved)->not->toBeNull()
        ->and($resolved->is($voucher))->toBeTrue();
});

it('returns null when compiled claim submission voucher does not exist', function () {
    $submission = new CompiledClaimSubmissionData(
        code: 'MISSING123',
        inputs: []
    );

    expect(app(ResolveVoucherForCompiledClaimSubmission::class)
        ->handle($submission)
    )->toBeNull();
});
