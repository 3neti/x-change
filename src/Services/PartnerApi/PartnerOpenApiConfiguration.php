<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\PartnerApi;

use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\Scramble;

final readonly class PartnerOpenApiConfiguration
{
    public static function register(): GeneratorConfig
    {
        return Scramble::registerApi('partner', [
            'api_path' => trim((string) config('x-change.partner_api.prefix', 'api/partner/v1'), '/'),
            'export_path' => 'resources/api/x-change-partner-api.openapi.json',
            'info' => [
                'version' => '1.5.0',
                'description' => 'Approved Partner API for prefunded issuance and customer-funded Pay Code orders.',
            ],
            'ui' => ['title' => 'X-Change Partner API'],
        ])->expose(false)->afterOpenApiGenerated(new PartnerOpenApiDocumentTransformer);
    }
}
