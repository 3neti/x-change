<?php

declare(strict_types=1);

use LBHurtado\XChange\Services\Configuration\FrontendRuntimeDependencies;

it('declares the runtime dependencies required by package-owned frontend assets', function () {
    expect(app(FrontendRuntimeDependencies::class)->npmInstallCommand())->toBe([
        'npm',
        'install',
        '--save',
        '@laravel/echo-vue@^2.4.0',
        '@vueuse/core@^12.8.2',
        'lucide-vue-next@^0.468.0',
        'marked@^18.0.7',
        'reka-ui@^2.10.1',
    ]);
});
