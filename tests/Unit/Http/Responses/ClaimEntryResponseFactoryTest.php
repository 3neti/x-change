<?php

use Inertia\Response;
use LBHurtado\XChange\Http\Responses\ClaimEntryResponseFactory;

it('renders the claim entry inertia response', function () {
    if (! class_exists(\Inertia\Inertia::class)) {
        $this->markTestSkipped('Inertia is not installed in this test environment.');
    }

    $response = app(ClaimEntryResponseFactory::class)->render(
        initialCode: 'TEST123',
        claimExperience: [
            'phases' => [],
        ],
    );

    expect($response)->toBeInstanceOf(Response::class);
});
