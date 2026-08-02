<?php

declare(strict_types=1);

use LBHurtado\XChange\Console\Commands\InstallXChangeCommand;

it('delegates every installer publication to the unified catalog', function (): void {
    $source = file_get_contents((new ReflectionClass(InstallXChangeCommand::class))->getFileName());

    expect($source)
        ->toContain("'x-change:publish'")
        ->not->toContain("'vendor:publish'")
        ->not->toContain('FormHandlerKYC')
        ->not->toContain('XRiderServiceProvider')
        ->not->toContain('XRayServiceProvider');
});
