<?php

declare(strict_types=1);

it('publishes the package cockpit implementation namespace with the x-change ui assets', function () {
    $provider = file_get_contents(dirname(__DIR__, 3).'/src/Providers/XChangeServiceProvider.php');

    expect($provider)->toContain('resources/js/cockpit')
        ->and($provider)->toContain("resource_path('js/cockpit')");
});
