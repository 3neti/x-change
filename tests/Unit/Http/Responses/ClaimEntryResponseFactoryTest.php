<?php

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\XChange\Http\Responses\ClaimEntryResponseFactory;

it('renders the claim entry inertia response', function () {
    if (! class_exists(Inertia::class)) {
        $this->markTestSkipped('Inertia is not installed in this test environment.');
    }

    $response = app(ClaimEntryResponseFactory::class)->render(
        initialCode: 'TEST123',
        claimExperience: [
            'phases' => [],
        ],
        provisioningRequirement: [
            'provider' => 'netbank',
            'descriptor' => [
                'title' => 'Add payout destination',
            ],
        ],
    );

    expect($response)->toBeInstanceOf(Response::class);
});

it('only exposes a receipt summary for a fully collected payment handoff', function (): void {
    $factory = app(ClaimEntryResponseFactory::class);
    $summary = [
        'amount_paid_minor' => 10000,
        'currency' => 'PHP',
        'completed_at' => '2026-08-26T08:30:00+08:00',
    ];

    $paid = $factory->paymentHandoff('PAID', '/x/pay/PAID', true, $summary);
    $unpaid = $factory->paymentHandoff('OPEN', '/x/pay/OPEN', false, $summary);
    $request = Request::create('/x/claim/PAID');
    $request->headers->set('X-Inertia', 'true');

    expect($paid)->toBeInstanceOf(Response::class)
        ->and($paid->toResponse($request)->getData(true)['props']['receipt_summary'])
        ->toBe($summary)
        ->and($unpaid->toResponse($request)->getData(true)['props']['receipt_summary'])
        ->toBeNull();
});
