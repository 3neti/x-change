<?php

declare(strict_types=1);

it('requires an explicit commit before restoring an unsubmitted payout correction', function (): void {
    $this->artisan('xchange:disbursement:restore-unsubmitted-correction', [
        'code' => 'F6BG',
        '--evidence-reference' => 'netbank-dashboard:no-operation:F6BG-R1',
        '--confirm-provider-not-submitted' => true,
        '--json' => true,
    ])->expectsOutput(json_encode([
        'schema' => 'x-change.unsubmitted-payout-correction-restoration.v1',
        'success' => false,
        'mode' => 'preview',
        'pay_code' => 'F6BG',
        'message' => 'No changes were made. Pass --commit after verifying provider evidence.',
    ], JSON_UNESCAPED_SLASHES))->assertFailed();
});
