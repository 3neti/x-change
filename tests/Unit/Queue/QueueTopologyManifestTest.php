<?php

declare(strict_types=1);

use Illuminate\Contracts\Queue\ShouldQueue;
use LBHurtado\XChange\Jobs\Funding\ResumeOnDemandPayCodeIssuanceJob;
use LBHurtado\XChange\Queue\QueueTopologyManifest;

it('advertises and loads the versioned package queue manifest', function (): void {
    $composer = json_decode(
        file_get_contents(dirname(__DIR__, 3).'/composer.json'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
    $topology = app(QueueTopologyManifest::class);
    $manifest = $topology->load();

    expect(data_get($composer, 'extra.settlement-os.queue-manifest'))
        ->toBe('resources/settlement-os/queues.php')
        ->and($manifest['schema'])->toBe(QueueTopologyManifest::Schema)
        ->and($manifest['package'])->toBe(QueueTopologyManifest::Package)
        ->and(array_keys($manifest['lanes']))->toBe([
            'funding',
            'feedback',
            'partner-payments',
            'issuance',
        ]);
});

it('declares every package queued job exactly once', function (): void {
    $sourceRoot = dirname(__DIR__, 3).'/src/Jobs';
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceRoot));
    $discovered = [];

    foreach ($iterator as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $relative = substr($file->getPathname(), strlen($sourceRoot) + 1, -4);
        $class = 'LBHurtado\\XChange\\Jobs\\'.str_replace(DIRECTORY_SEPARATOR, '\\', $relative);

        if (class_exists($class) && is_subclass_of($class, ShouldQueue::class)) {
            $discovered[] = $class;
        }
    }

    sort($discovered);
    $declared = app(QueueTopologyManifest::class)->jobClasses();
    sort($declared);

    expect($declared)->toBe($discovered)
        ->and($declared)->toHaveCount(17);
});

it('resolves configurable queue selectors without silently merging lanes', function (): void {
    config()->set('x-change.redemption.feedback.queue', 'custom-feedback');

    $resolved = collect(app(QueueTopologyManifest::class)->resolved())->keyBy('lane');

    expect($resolved['funding']['effective_queues'])->toBe(['x-change-funding'])
        ->and($resolved['feedback']['effective_queues'])
        ->toBe(['custom-feedback', 'x-change-feedback'])
        ->and($resolved['partner-payments']['effective_queues'])->toBe(['partner-payments'])
        ->and($resolved['issuance']['effective_queues'])->toBe(['x-change-issuance'])
        ->and($resolved['issuance']['status'])->toBe('active');
});

it('routes on-demand issuance recovery to its explicit configurable lane', function (): void {
    expect((new ResumeOnDemandPayCodeIssuanceJob(41))->queue)->toBe('x-change-issuance');

    config()->set('x-change.issuance_funding.on_demand.queue', 'host-approved-issuance');

    expect((new ResumeOnDemandPayCodeIssuanceJob(42))->queue)->toBe('host-approved-issuance');
});

it('gives every queued job sanitized operational tags only', function (): void {
    $topology = app(QueueTopologyManifest::class);

    foreach ($topology->jobClasses() as $jobClass) {
        $job = (new ReflectionClass($jobClass))->newInstanceWithoutConstructor();

        expect($job->tags())->toBe([
            'package:x-change',
            'lane:'.$topology->laneFor($jobClass),
            'job:'.class_basename($jobClass),
        ]);
    }
});

it('keeps Horizon optional for the reusable x-change package', function (): void {
    $composer = json_decode(
        file_get_contents(dirname(__DIR__, 3).'/composer.json'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect(data_get($composer, 'require.laravel/horizon'))->toBeNull()
        ->and(data_get($composer, 'suggest.laravel/horizon'))->toBeString();
});
