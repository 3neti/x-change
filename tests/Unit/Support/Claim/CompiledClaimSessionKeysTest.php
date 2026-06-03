<?php

declare(strict_types=1);

use LBHurtado\XChange\Support\Claim\CompiledClaimSessionKeys;

it('freezes compiled claim handoff session keys', function () {
    expect(CompiledClaimSessionKeys::SUBMISSION)->toBe('compiled_claim_submission')
        ->and(CompiledClaimSessionKeys::PREPARED)->toBe('compiled_claim_prepared');
});
