<?php

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
