<?php

declare(strict_types=1);

use LBHurtado\XChange\Console\Commands\SetupXChangeCommand;

it('loads an adopted host model in a fresh PHP process before provisioning', function (): void {
    $source = file_get_contents(
        (new ReflectionClass(SetupXChangeCommand::class))->getFileName(),
    );
    $adoption = strpos($source, "\$this->call('x-change:host:adopt'");
    $installation = strpos($source, "'x-change:install'", $adoption);
    $process = strpos($source, 'Process::path(base_path())', $installation);

    expect($adoption)->toBeInt()
        ->and($installation)->toBeInt()->toBeGreaterThan($adoption)
        ->and($process)->toBeInt()->toBeGreaterThan($installation)
        ->and($source)->not->toContain("\$this->call('x-change:install'");
});
