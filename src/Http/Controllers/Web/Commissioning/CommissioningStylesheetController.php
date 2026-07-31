<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Commissioning;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class CommissioningStylesheetController
{
    public function __invoke(): BinaryFileResponse
    {
        return response()->file(dirname(__DIR__, 5).'/resources/css/commissioning.css', [
            'Cache-Control' => 'public, max-age=86400',
            'Content-Type' => 'text/css; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
