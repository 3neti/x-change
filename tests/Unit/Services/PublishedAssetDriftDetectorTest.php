<?php

declare(strict_types=1);

use LBHurtado\XChange\Services\PublishedAssetDriftDetector;

use function PHPUnit\Framework\assertFileExists;

function cockpitDriftTempPath(string $suffix): string
{
    return sys_get_temp_dir().'/x-change-cockpit-drift-'.bin2hex(random_bytes(6)).'/'.$suffix;
}

it('reports published cockpit assets as synchronized when source and target match', function () {
    $source = cockpitDriftTempPath('source');
    $target = cockpitDriftTempPath('target');

    mkdir($source.'/components', 0777, true);
    mkdir($target.'/components', 0777, true);

    file_put_contents($source.'/components/Panel.vue', "<template>\n    <section>Panel</section>\n</template>\n");
    file_put_contents($target.'/components/Panel.vue', "<template>\n    <section>Panel</section>\n</template>\n");

    $result = (new PublishedAssetDriftDetector)->inspect([
        $source => $target,
    ]);

    expect($result['passed'])->toBeTrue()
        ->and($result['summary']['checked'])->toBe(1)
        ->and($result['summary']['stale'])->toBe(0)
        ->and($result['summary']['missing'])->toBe(0)
        ->and($result['summary']['extra'])->toBe(0)
        ->and($result['files'])->toHaveCount(1)
        ->and($result['files'][0]['status'])->toBe('ok');
});

it('reports stale missing and extra published cockpit assets', function () {
    $source = cockpitDriftTempPath('source');
    $target = cockpitDriftTempPath('target');

    mkdir($source, 0777, true);
    mkdir($target, 0777, true);

    file_put_contents($source.'/stale.ts', 'export const value = "package";'.PHP_EOL);
    file_put_contents($target.'/stale.ts', 'export const value = "host";'.PHP_EOL);
    file_put_contents($source.'/missing.ts', 'export const missing = true;'.PHP_EOL);
    file_put_contents($target.'/extra.ts', 'export const extra = true;'.PHP_EOL);

    $result = (new PublishedAssetDriftDetector)->inspect([
        $source => $target,
    ]);

    expect($result['passed'])->toBeFalse()
        ->and($result['summary']['checked'])->toBe(3)
        ->and($result['summary']['stale'])->toBe(1)
        ->and($result['summary']['missing'])->toBe(1)
        ->and($result['summary']['extra'])->toBe(1)
        ->and(collect($result['files'])->pluck('status')->all())
        ->toEqualCanonicalizing(['stale', 'missing', 'extra']);
});

it('ignores generated install headers while comparing published cockpit assets', function () {
    $source = cockpitDriftTempPath('source');
    $target = cockpitDriftTempPath('target');

    mkdir($source, 0777, true);
    mkdir($target, 0777, true);

    file_put_contents($source.'/types.ts', 'export type Status = "ok";'.PHP_EOL);

    $detector = new PublishedAssetDriftDetector;
    file_put_contents(
        $target.'/types.ts',
        $detector->withGeneratedHeader('export type Status = "ok";'.PHP_EOL, 'types.ts')
    );

    $result = $detector->inspect([
        $source => $target,
    ]);

    expect($result['passed'])->toBeTrue()
        ->and($result['summary']['stale'])->toBe(0)
        ->and($result['files'][0]['status'])->toBe('ok');
});

it('generates TypeScript warning headers without trailing whitespace', function () {
    $header = (new PublishedAssetDriftDetector)->withGeneratedHeader(
        'export type Status = "ok";'.PHP_EOL,
        'types.ts',
    );

    expect($header)->not->toMatch('/[ \t]+$/m')
        ->and($header)->toContain(PublishedAssetDriftDetector::GeneratedHeaderId)
        ->and($header)->toContain('export type Status = "ok";');
});

it('can apply generated install headers to published cockpit files without changing semantic content', function () {
    $source = cockpitDriftTempPath('source');
    $target = cockpitDriftTempPath('target');

    mkdir($source.'/pages', 0777, true);
    mkdir($target.'/pages', 0777, true);

    file_put_contents($source.'/pages/Dashboard.vue', "<template>\n    <main>Dashboard</main>\n</template>\n");
    file_put_contents($target.'/pages/Dashboard.vue', "<template>\n    <main>Dashboard</main>\n</template>\n");

    $detector = new PublishedAssetDriftDetector;
    $result = $detector->applyGeneratedHeaders([
        $source => $target,
    ]);

    assertFileExists($target.'/pages/Dashboard.vue');

    expect($result['updated'])->toBe(1)
        ->and(file_get_contents($target.'/pages/Dashboard.vue'))->toContain(PublishedAssetDriftDetector::GeneratedHeaderId)
        ->and($detector->inspect([$source => $target])['passed'])->toBeTrue();
});

it('does not stamp headers by overwriting stale published cockpit files', function () {
    $source = cockpitDriftTempPath('source');
    $target = cockpitDriftTempPath('target');

    mkdir($source, 0777, true);
    mkdir($target, 0777, true);

    file_put_contents($source.'/QuickGenerate.vue', '<template>Package</template>'.PHP_EOL);
    file_put_contents($target.'/QuickGenerate.vue', '<template>Host edit</template>'.PHP_EOL);

    $detector = new PublishedAssetDriftDetector;
    $result = $detector->applyGeneratedHeaders([
        $source => $target,
    ]);

    expect($result['updated'])->toBe(0)
        ->and($result['skipped'])->toBe(1)
        ->and(file_get_contents($target.'/QuickGenerate.vue'))->toBe('<template>Host edit</template>'.PHP_EOL);
});
