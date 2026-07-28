<?php

use LBHurtado\XChange\ClaimWalkthrough\RiderOgPreviewPayloadFactory;
use LBHurtado\XChange\ClaimWalkthrough\RiderStampPreviewPayloadFactory;

it('projects a presentation-only Rider Stamp from structured instructions', function () {
    $payload = (new RiderStampPreviewPayloadFactory)->make([
        'amount' => '125.00',
        'rider' => [
            'message' => 'Legacy Rider Message',
            'splash' => '<h1>Rider Splash</h1>',
            'og_source' => 'message',
            'stamp' => [
                'source' => 'splash',
                'title' => 'A custom Rider Stamp',
                'description' => 'Presentation only.',
                'fit' => 'contain',
                'position' => 'top',
                'scrim' => 36,
                'theme' => 'dark',
                'artwork_source' => 'url',
                'artwork_treatment' => 'artwork',
                'copy_source' => 'message',
                'show_logo' => false,
                'show_tagline' => true,
                'claim_marker' => 'both',
                'claim_marker_position' => 'top_right',
                'version' => 2,
            ],
        ],
    ]);

    expect($payload)->toMatchArray([
        'source' => 'splash',
        'label' => 'Rider Stamp Preview',
        'title' => 'A custom Rider Stamp',
        'description' => 'Presentation only.',
        'reference' => 'rider.splash',
        'stamp' => [
            'source' => 'splash',
            'artwork_source' => 'url',
            'artwork_treatment' => 'artwork',
            'copy_source' => 'message',
            'show_logo' => false,
            'show_tagline' => true,
            'claim_marker' => 'both',
            'claim_marker_position' => 'top_right',
            'fit' => 'contain',
            'position' => 'top',
            'scrim' => 36,
            'theme' => 'dark',
            'version' => 2,
            'presentation_only' => true,
        ],
    ])->and(data_get($payload, 'og_meta.title'))->toBe('A custom Rider Stamp');
});

it('maps legacy OG source into the Rider Stamp projection', function () {
    $payload = (new RiderStampPreviewPayloadFactory)->make([
        'rider' => [
            'message' => 'A legacy message',
            'og_source' => 'message',
        ],
    ]);

    expect($payload['source'])->toBe('message')
        ->and($payload['title'])->toBe('A legacy message')
        ->and($payload['stamp']['presentation_only'])->toBeTrue();
});

it('preserves the legacy OG preview source vocabulary', function () {
    $payload = (new RiderOgPreviewPayloadFactory)->make([
        'rider' => [],
    ]);

    expect($payload['source'])->toBe('default')
        ->and($payload['label'])->toBe('Rider Stamp Preview');
});
