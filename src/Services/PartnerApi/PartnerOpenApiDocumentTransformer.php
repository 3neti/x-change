<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\PartnerApi;

use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityRequirement;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Dedoc\Scramble\Support\Generator\Server;

final readonly class PartnerOpenApiDocumentTransformer
{
    public function __invoke(OpenApi $document): void
    {
        $document->servers = [Server::make('/'.trim((string) config('x-change.partner_api.prefix', 'api/partner/v1'), '/'))];
        $scheme = SecurityScheme::oauth2()->as('partnerOAuth')
            ->flow('clientCredentials', function ($flow): void {
                $flow->tokenUrl('/oauth/token');

                foreach ((array) config('x-change.partner_api.scopes', []) as $scope => $description) {
                    $flow->addScope((string) $scope, (string) $description);
                }
            });
        $document->components->addSecurityScheme('partnerOAuth', $scheme);

        $scopes = [
            'GET /capabilities' => 'capabilities:read',
            'POST /pay-code-estimates' => 'pay-codes:estimate',
            'POST /pay-codes' => 'pay-codes:issue',
            'GET /pay-codes/by-reference/{externalReference}' => 'pay-codes:read',
            'GET /pay-codes/{code}' => 'pay-codes:read',
            'POST /pay-codes/{code}/payment-attempts' => 'pay-codes:pay',
            'POST /pay-codes/{code}/cancellation' => 'pay-codes:cancel',
            'POST /funded-orders' => 'funded-orders:create',
            'GET /funded-orders/{reference}' => 'funded-orders:read',
            'POST /stored-value-collections' => 'stored-value:spend',
            'GET /stored-value-collections/{reference}' => 'stored-value:spend',
            'POST /stored-value-collections/{reference}/verification' => 'stored-value:spend',
            'POST /stored-value-collections/{reference}/commit' => 'stored-value:spend',
            'POST /stored-value-instruments/{instrument}/spend-challenges' => 'stored-value:spend',
            'POST /stored-value-instruments/{instrument}/spend-challenges/{challenge}/verification' => 'stored-value:spend',
            'POST /stored-value-instruments/{instrument}/spends' => 'stored-value:spend',
            'GET /stored-value-instruments/{instrument}/transactions' => 'stored-value:read',
        ];

        foreach ($document->paths as $path) {
            foreach ($path->operations as $operation) {
                $key = strtoupper($operation->method).' /'.ltrim($path->path, '/');
                $scope = $scopes[$key] ?? null;

                if ($scope !== null) {
                    $operation->security = [new SecurityRequirement(['partnerOAuth' => [$scope]])];
                }
            }
        }
    }
}
