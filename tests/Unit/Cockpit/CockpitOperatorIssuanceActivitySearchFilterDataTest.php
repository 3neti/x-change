<?php

declare(strict_types=1);

use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivitySearchFilterData;

it('normalizes operator activity search and filter inputs for read-only discovery', function () {
    $filters = CockpitOperatorIssuanceActivitySearchFilterData::normalize(
        search: '  PC-1234  ',
        statuses: [' issued ', '', 'issued', 'failed'],
        handoffStatuses: [' recorded ', 'not_wired', '', 'recorded'],
    );

    expect($filters->search)->toBe('PC-1234')
        ->and($filters->statuses)->toBe(['issued', 'failed'])
        ->and($filters->handoffStatuses)->toBe(['recorded', 'not_wired'])
        ->and($filters->isEmpty())->toBeFalse()
        ->and($filters->toArray())->toBe([
            'search' => 'PC-1234',
            'statuses' => ['issued', 'failed'],
            'handoffStatuses' => ['recorded', 'not_wired'],
        ]);
});

it('treats blank operator activity search filters as empty', function () {
    $filters = CockpitOperatorIssuanceActivitySearchFilterData::normalize(
        search: '   ',
        statuses: ['', '   '],
        handoffStatuses: null,
    );

    expect($filters->search)->toBeNull()
        ->and($filters->statuses)->toBe([])
        ->and($filters->handoffStatuses)->toBe([])
        ->and($filters->isEmpty())->toBeTrue();
});
