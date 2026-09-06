<?php

declare(strict_types=1);

use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Services\DisbursementRejectionTrustService;

it('trusts a terminal provider response without a transaction identifier', function (): void {
    $reconciliation = new DisbursementReconciliation([
        'status' => 'failed',
        'provider' => 'netbank',
        'provider_transaction_id' => '',
        'needs_review' => false,
        'completed_at' => now(),
        'meta' => [
            'provider_response' => [
                'received' => true,
                'status' => 'failed',
            ],
        ],
    ]);

    expect(app(DisbursementRejectionTrustService::class)->isTrusted($reconciliation))
        ->toBeTrue();
});

it('does not trust an exception-derived failure without terminal provider evidence', function (): void {
    $reconciliation = new DisbursementReconciliation([
        'status' => 'failed',
        'provider' => 'unknown',
        'provider_transaction_id' => null,
        'needs_review' => false,
        'completed_at' => null,
        'meta' => [],
    ]);

    expect(app(DisbursementRejectionTrustService::class)->isTrusted($reconciliation))
        ->toBeFalse();
});

it('never trusts a provider outcome marked for review', function (): void {
    $reconciliation = new DisbursementReconciliation([
        'status' => 'failed',
        'provider' => 'netbank',
        'provider_transaction_id' => 'NETBANK-UNKNOWN-001',
        'needs_review' => true,
        'completed_at' => now(),
    ]);

    expect(app(DisbursementRejectionTrustService::class)->isTrusted($reconciliation))
        ->toBeFalse();
});
