<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\PartnerApi;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use RuntimeException;

class ShowPartnerApiOpenApiController extends Controller
{
    public function __invoke(): Response
    {
        $path = dirname(__DIR__, 4).'/resources/api/x-change-partner-api.openapi.json';
        $document = file_get_contents($path);

        if (! is_string($document)) {
            throw new RuntimeException('The X-Change Partner API contract is unavailable.');
        }

        return response($document, 200, [
            'Content-Type' => 'application/vnd.oai.openapi+json; charset=UTF-8',
        ]);
    }
}
