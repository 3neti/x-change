<?php

declare(strict_types=1);

use function Pest\Laravel\postJson;

it('creates an issuer through the lifecycle route surface', function () {
    $payload = [
        // Mirror the same payload used in your existing onboarding feature test.
        // Reuse that exact structure so behavior stays aligned.
    ];

    $response = postJson('/api/x/v1/issuers', $payload);

    $response->assertSuccessful();
});
