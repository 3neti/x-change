<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Tests\Contract\OpenApi;

use Orchestra\Testbench\TestCase;

abstract class OpenApiTestCase extends TestCase
{
    protected function openApiSpecPath(): string
    {
        $override = env('XCHANGE_OPENAPI_SPEC');

        if (is_string($override) && $override !== '') {
            return $override;
        }

        return dirname(__DIR__, 3).'/x-change-lifecycle-api.public.json';
    }
}
