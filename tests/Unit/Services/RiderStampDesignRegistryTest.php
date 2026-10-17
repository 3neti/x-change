<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use LBHurtado\Voucher\Data\RiderStampData;
use LBHurtado\XChange\Http\Requests\EstimatePayCodeRequest;
use LBHurtado\XChange\Http\Requests\GeneratePayCodeRequest;
use LBHurtado\XChange\Services\RiderStampDesignRegistry;

it('materializes the configured default design for every new issuance', function (): void {
    $payload = app(RiderStampDesignRegistry::class)->materialize([
        'rider' => [
            'message' => 'Thank you',
            'stamp' => null,
        ],
    ]);

    expect(data_get($payload, 'rider.stamp'))->toMatchArray([
        'version' => 3,
        'design_id' => 'steampunk',
        'design_version' => 1,
    ]);
});

it('preserves an approved theme design while fixing its version', function (): void {
    $payload = app(RiderStampDesignRegistry::class)->materialize([
        'rider' => [
            'stamp' => [
                'design_id' => 'x-change-steampunk',
                'design_version' => 1,
                'show_logo' => false,
            ],
        ],
    ]);

    expect(data_get($payload, 'rider.stamp'))->toMatchArray([
        'version' => 3,
        'design_id' => 'x-change-steampunk',
        'design_version' => 1,
        'show_logo' => false,
    ]);
});

it('rejects unregistered or stale design identities at the service boundary', function (
    string $designId,
    int $designVersion,
): void {
    app(RiderStampDesignRegistry::class)->materialize([
        'rider' => [
            'stamp' => [
                'design_id' => $designId,
                'design_version' => $designVersion,
            ],
        ],
    ]);
})->throws(InvalidArgumentException::class)->with([
    'unregistered identity' => ['outside-theme', 1],
    'stale version' => ['x-change-amber', 99],
]);

it('exposes the canonical raster palette for renderer parity', function (): void {
    expect(
        app(RiderStampDesignRegistry::class)->palette('x-change-amber', 1),
    )->toBe([
        'start' => [67, 20, 7],
        'end' => [124, 45, 18],
        'glow_primary' => [251, 146, 60],
        'glow_secondary' => [245, 158, 11],
    ]);
});

it('bridges the design snapshot through extension metadata until voucher v3 is adopted', function (): void {
    $registry = app(RiderStampDesignRegistry::class);
    $payload = $registry->normalizeForInstalledVoucherContract(
        $registry->materialize(['rider' => ['stamp' => null]]),
    );

    if (defined(RiderStampData::class.'::COMPOSITION_SCHEMA_VERSION')) {
        expect(data_get($payload, 'rider.stamp'))->toMatchArray([
            'version' => 3,
            'design_id' => 'steampunk',
            'design_version' => 1,
        ]);

        return;
    }

    expect(data_get($payload, 'rider.stamp.version'))->toBe(2)
        ->and(data_get($payload, 'rider.stamp.design_id'))->toBeNull()
        ->and(data_get($payload, 'metadata.custom.rider_stamp_design'))->toBe([
            'id' => 'steampunk',
            'version' => 1,
        ]);
});

it('retains historical palettes after a design default advances', function (): void {
    $design = config('x-change.experience.stamp_designs.x-change-amber');
    data_set($design, 'default_version', 2);
    data_set($design, 'versions.2.palette', [
        'start' => [1, 2, 3],
        'end' => [4, 5, 6],
        'glow_primary' => [7, 8, 9],
        'glow_secondary' => [10, 11, 12],
    ]);
    config()->set('x-change.experience.stamp_designs.x-change-amber', $design);

    expect(
        app(RiderStampDesignRegistry::class)->palette('x-change-amber', 1),
    )->toBe([
        'start' => [67, 20, 7],
        'end' => [124, 45, 18],
        'glow_primary' => [251, 146, 60],
        'glow_secondary' => [245, 158, 11],
    ]);
});

it('accepts independently introduced revisions without hardcoded request versions', function (): void {
    config()->set('x-change.experience.stamp_designs.x-change-amber.versions.2', config('x-change.experience.stamp_designs.x-change-amber.versions.1'));
    foreach ([GeneratePayCodeRequest::class, EstimatePayCodeRequest::class] as $class) {
        $request = new $class;
        $request->merge(['rider' => ['stamp' => ['design_id' => 'x-change-amber']]]);
        $rules = $request->rules()['rider.stamp.design_version'];
        $validator = Validator::make(['rider' => ['stamp' => ['design_version' => 2]]], ['rider.stamp.design_version' => $rules]);
        expect($validator->passes())->toBeTrue();
    }
});
