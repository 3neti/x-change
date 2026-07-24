<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use LBHurtado\XChange\Http\Middleware\ShareXChangeBranding;

it('acknowledges completion without logging beneficiary data', function (): void {
    $this->withoutMiddleware(ShareXChangeBranding::class);
    Log::spy();

    $this->postJson(route('x-change.claim.complete', ['code' => 'test-1234']), [
        'flow_id' => 'flow-secret-identifier',
        'collected_data' => [
            'mobile' => '+639171234567',
            'account_number' => '1234567890',
        ],
        'completed_at' => now()->toIso8601String(),
    ])->assertOk()
        ->assertExactJson([
            'success' => true,
            'message' => 'Flow completed, awaiting user confirmation',
        ]);

    Log::shouldHaveReceived('info')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => $message === '[ClaimCompleteController] Form flow callback received'
            && $context === [
                'voucher_code' => 'TEST-1234',
                'has_flow_id' => true,
            ]
        );
});
