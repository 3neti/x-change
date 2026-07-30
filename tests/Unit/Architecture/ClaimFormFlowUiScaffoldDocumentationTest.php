<?php

declare(strict_types=1);

it('documents the public claim form-flow ui scaffold without cockpit scope', function () {
    $root = dirname(__DIR__, 3).'/docs/claim-ux';

    $report = file_get_contents($root.'/2026-07-30-form-flow-driver-ui-scaffold.md');
    $batch = file_get_contents($root.'/claim-storyboard-qa-batch-index.md');
    $matrix = file_get_contents($root.'/2026-07-30-storyboard-qa-matrix.md');
    $profile = file_get_contents($root.'/claim-ui-config-profile.md');
    $runbook = file_get_contents($root.'/storyboard-qa-runbook.md');

    expect($report)
        ->toContain('public claim and form-flow experience')
        ->toContain('It does not touch Cockpit')
        ->toContain('FORM_FLOW_UI_VARIANT')
        ->toContain('XCHANGE_CLAIM_UI_VARIANT')
        ->toContain('no-money claim walkthrough passed with `submitted_claim=false`')
        ->and($profile)
        ->toContain('x-change.claim.experience_ui')
        ->toContain('Do not put Cockpit-specific copy')
        ->toContain('Do not use this profile to decide claim execution')
        ->and($batch)
        ->toContain('QA batch index renderer')
        ->toContain('claim-walkthrough-qa-batch.html')
        ->toContain('claim-walkthrough-qa-review.md')
        ->toContain('storage/app/x-change/claim-preview-batches/{run_id}')
        ->toContain('artifacts.view_options')
        ->toContain('Markdown review worksheet')
        ->toContain('review_checklist')
        ->toContain('allowed_statuses')
        ->toContain('needs_fix')
        ->toContain('--qa-review')
        ->toContain('--qa-review-output')
        ->toContain('claim-walkthrough-qa-review-summary.json')
        ->toContain('--qa-diff-from')
        ->toContain('--qa-acceptance')
        ->toContain('claim-ux-acceptance-report.md')
        ->toContain('Visual Polish Priorities')
        ->toContain('Review checklist')
        ->toContain('Reviewer status')
        ->toContain('default` points to the HTML index')
        ->toContain('submit_claim=false')
        ->toContain('It does not')
        ->toContain('call payout providers')
        ->toContain('The batch renderer depends on the matrix safety flags')
        ->and($matrix)
        ->toContain('xchange:claim-walkthrough --qa-matrix --json')
        ->toContain('Money movement: disabled')
        ->toContain('claim_fake_otp_handler_preview')
        ->toContain('claim_fake_kyc_handler_preview')
        ->toContain('claim_mocked_location_handler_preview')
        ->toContain('claim_mocked_selfie_handler_preview')
        ->toContain('claim_signature_handler_preview')
        ->toContain('No handler lanes remain planned')
        ->toContain('ClaimHandlerPreviewScenarioFactory')
        ->toContain('xchange:claim-walkthrough --qa-batch')
        ->toContain('The fake OTP handler preview is not Paynamics OTP')
        ->toContain('Do not add Cockpit routes')
        ->and($runbook)
        ->toContain('Do not use Cockpit pages')
        ->toContain('Do not pass `--submit-claim`')
        ->toContain('xchange:claim-walkthrough --qa-matrix --json')
        ->toContain('--qa-batch')
        ->toContain('claim-storyboard-qa-batch-index.md')
        ->toContain('claim_fake_otp_handler_preview')
        ->toContain('claim_fake_kyc_handler_preview')
        ->toContain('claim_mocked_location_handler_preview')
        ->toContain('claim_mocked_selfie_handler_preview')
        ->toContain('claim_signature_handler_preview')
        ->toContain('redeemer-side mobile verification')
        ->toContain('"submitted_claim": false');
});
