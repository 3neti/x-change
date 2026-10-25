<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\PartnerApi;

use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Illuminate\Console\Command;
use LBHurtado\XChange\Services\PartnerApi\PartnerOpenApiConfiguration;
use RuntimeException;

final class ExportPartnerOpenApiCommand extends Command
{
    protected $signature = 'x-change:partner-api:export-openapi {--check : Fail when the published artifact differs from the generated contract}';

    protected $description = 'Generate the reviewed Partner OpenAPI contract from live routes and the curated prefunded baseline';

    public function handle(): int
    {
        if (! class_exists(Scramble::class)) {
            $this->error('Install the development dependency dedoc/scramble before exporting the Partner API contract.');

            return self::FAILURE;
        }

        $root = dirname(__DIR__, 4);
        $baselinePath = $root.'/resources/api/x-change-partner-api.base.json';
        $artifactPath = $root.'/resources/api/x-change-partner-api.openapi.json';
        $baseline = json_decode((string) file_get_contents($baselinePath), true, flags: JSON_THROW_ON_ERROR);
        $generated = app(Generator::class)(PartnerOpenApiConfiguration::register());
        $requiredPaths = [
            '/funded-orders' => 'post',
            '/funded-orders/{reference}' => 'get',
            '/stored-value-collections' => 'post',
            '/stored-value-collections/{reference}' => 'get',
            '/stored-value-collections/{reference}/verification' => 'post',
            '/stored-value-collections/{reference}/commit' => 'post',
        ];

        foreach (array_keys((array) ($baseline['paths'] ?? [])) as $path) {
            foreach (array_keys((array) $baseline['paths'][$path]) as $method) {
                if (! isset($generated['paths'][$path][$method])) {
                    throw new RuntimeException("The existing Partner API route {$method} {$path} is absent from Scramble.");
                }
            }
        }

        foreach ($requiredPaths as $path => $method) {
            if (! isset($generated['paths'][$path][$method])) {
                throw new RuntimeException("The funded-order route {$method} {$path} is absent from Scramble.");
            }
        }

        $unexpectedPaths = array_diff(
            array_keys((array) ($generated['paths'] ?? [])),
            [...array_keys((array) ($baseline['paths'] ?? [])), ...array_keys($requiredPaths)],
        );

        if ($unexpectedPaths !== []) {
            throw new RuntimeException('Undocumented Partner API routes: '.implode(', ', $unexpectedPaths));
        }

        if (data_get($generated, 'paths./funded-orders.post.security.0.partnerOAuth') !== ['funded-orders:create']
            || data_get($generated, 'paths./funded-orders/{reference}.get.security.0.partnerOAuth') !== ['funded-orders:read']) {
            throw new RuntimeException('Generated funded-order OAuth scopes do not match the Partner contract.');
        }

        foreach ([
            '/stored-value-collections' => 'post',
            '/stored-value-collections/{reference}' => 'get',
            '/stored-value-collections/{reference}/verification' => 'post',
            '/stored-value-collections/{reference}/commit' => 'post',
        ] as $path => $method) {
            if (data_get($generated, "paths.{$path}.{$method}.security.0.partnerOAuth") !== ['stored-value:spend']) {
                throw new RuntimeException("Generated stored-value collection scope for {$method} {$path} is not authorized.");
            }

            $baseline['paths'][$path] = [$method => $generated['paths'][$path][$method]];
        }

        $baseline['info']['version'] = '1.5.0';
        $baseline['info']['description'] = 'Approved Partner API for prefunded issuance and customer-funded Pay Code orders. Funded orders use the commissioned Commercial Principal and require provider-verified payment before issuance.';
        $baseline['components']['securitySchemes']['partnerOAuth']['flows']['clientCredentials']['scopes']['funded-orders:create'] = 'Create a customer-funded Pay Code checkout.';
        $baseline['components']['securitySchemes']['partnerOAuth']['flows']['clientCredentials']['scopes']['funded-orders:read'] = 'Read customer-funded checkouts created by this client.';
        $baseline['components']['schemas']['FundedOrderRequest'] = [
            'allOf' => [
                ['$ref' => '#/components/schemas/PayCodeInstructions'],
                [
                    'type' => 'object',
                    'required' => ['funding_method'],
                    'properties' => [
                        'funding_method' => ['type' => 'string', 'enum' => ['qr_ph', 'bank_transfer']],
                        'payer_mobile' => ['type' => ['string', 'null'], 'description' => 'Required for bank transfer; a Philippine mobile number.'],
                    ],
                ],
            ],
        ];
        $baseline['components']['schemas']['FundedOrder'] = [
            'type' => 'object',
            'required' => ['reference', 'checkout_reference', 'status', 'funding_method', 'amount_minor', 'currency', 'payment_required', 'funding', 'needs_review'],
            'properties' => [
                'reference' => ['type' => 'string', 'description' => 'Order ULID used for polling.'],
                'checkout_reference' => ['type' => 'string'],
                'status' => ['type' => 'string'],
                'funding_method' => ['type' => 'string', 'enum' => ['qr_ph', 'bank_transfer']],
                'amount_minor' => ['type' => 'integer'],
                'currency' => ['type' => 'string'],
                'expires_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                'payment_required' => ['type' => 'boolean', 'description' => 'Only true while this order still accepts one exact payment.'],
                'funding' => ['type' => ['object', 'null'], 'description' => 'Selected payment instructions while payment is still accepted.'],
                'pay_code' => ['type' => ['object', 'null'], 'description' => 'Code and claim_url are present only after issuance.'],
                'needs_review' => ['type' => 'boolean'],
                'refund_status' => ['type' => ['string', 'null']],
            ],
        ];
        $baseline['components']['schemas']['FundedOrderSuccessEnvelope'] = [
            'type' => 'object',
            'required' => ['success', 'data', 'meta'],
            'properties' => [
                'success' => ['const' => true],
                'data' => ['$ref' => '#/components/schemas/FundedOrder'],
                'meta' => ['type' => 'object'],
            ],
        ];
        $response = static fn (string $description): array => [
            'description' => $description,
            'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/FundedOrderSuccessEnvelope']]],
        ];
        $baseline['paths']['/funded-orders'] = [
            'post' => [
                'operationId' => 'createPartnerFundedOrder',
                'summary' => 'Create a customer-funded Pay Code order',
                'description' => 'A single Pay Code is issued after exact provider-verified payment. Public issuance policy applies; Pay Code funding is unavailable.',
                'security' => [['partnerOAuth' => ['funded-orders:create']]],
                'parameters' => [[
                    'name' => 'Idempotency-Key', 'in' => 'header', 'required' => true,
                    'schema' => ['type' => 'string', 'maxLength' => 160],
                ]],
                'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/FundedOrderRequest']]]],
                'responses' => [
                    '201' => $response('Funding order created with payment instructions'),
                    '200' => $response('Idempotent replay of the same order'),
                    '401' => ['$ref' => '#/components/responses/Unauthorized'],
                    '403' => ['$ref' => '#/components/responses/Forbidden'],
                    '409' => ['description' => 'Idempotency key is bound to different terms'],
                    '422' => ['$ref' => '#/components/responses/ValidationError'],
                    '429' => ['description' => 'Rate or unpaid-order limit reached'],
                    '503' => ['description' => 'New funded orders are paused'],
                ],
            ],
        ];
        $baseline['paths']['/funded-orders/{reference}'] = [
            'get' => [
                'operationId' => 'showPartnerFundedOrder',
                'summary' => 'Poll a client-owned funded order',
                'security' => [['partnerOAuth' => ['funded-orders:read']]],
                'parameters' => [[
                    'name' => 'reference', 'in' => 'path', 'required' => true,
                    'schema' => ['type' => 'string', 'pattern' => '^[0-9A-HJKMNP-TV-Z]{26}$'],
                ]],
                'responses' => [
                    '200' => $response('Current order state; claim URL appears after issuance'),
                    '401' => ['$ref' => '#/components/responses/Unauthorized'],
                    '403' => ['$ref' => '#/components/responses/Forbidden'],
                    '404' => ['description' => 'Unknown order or owned by another client'],
                ],
            ],
        ];

        $encoded = json_encode($baseline, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";

        if ($this->option('check')) {
            if ((string) file_get_contents($artifactPath) !== $encoded) {
                $this->error('The published Partner OpenAPI artifact is stale. Run x-change:partner-api:export-openapi.');

                return self::FAILURE;
            }
        } else {
            file_put_contents($artifactPath, $encoded);
        }

        $this->info('Partner OpenAPI contract is current.');

        return self::SUCCESS;
    }
}
