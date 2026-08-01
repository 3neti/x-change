<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Configuration;

final readonly class FrontendRuntimeDependencies
{
    /**
     * @return list<string>
     */
    public function npmInstallCommand(): array
    {
        return [
            'npm',
            'install',
            '--save',
            '@laravel/echo-vue@^2.4.0',
            '@vueuse/core@^12.8.2',
            'axios@^1.16.0',
            'dompurify@^3.4.2',
            'lucide-vue-next@^0.468.0',
            'marked@^18.0.7',
            'reka-ui@^2.10.1',
        ];
    }
}
