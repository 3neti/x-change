<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\PartnerApi;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Services\ApiResponseFactory;
use LBHurtado\XChange\Services\PartnerApi\PartnerApiRequestContext;

class ShowPartnerCapabilitiesController extends Controller
{
    public function __invoke(
        PartnerApiRequestContext $context,
        ApiResponseFactory $responses,
    ): JsonResponse {
        $client = $context->client();

        return $responses->success([
            'schema' => 'x-change.partner-capabilities.v1',
            'client' => [
                'reference' => $client->reference,
                'name' => $client->name,
                'environment' => $client->environment,
            ],
            'operations' => array_values($client->scopes),
            'constraints' => $client->mandate,
            'requirements' => [
                'idempotency_key' => true,
                'correlation_id' => true,
            ],
        ]);
    }
}
