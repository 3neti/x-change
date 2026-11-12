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
            '/account-funded-orders' => 'post',
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
            || data_get($generated, 'paths./account-funded-orders.post.security.0.partnerOAuth') !== ['funded-orders:create', 'pay-codes:issue']
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

        // Scramble's inferred stored-value receipt types otherwise vary with host configuration.
        foreach (['/stored-value-collections/{reference}' => 'get', '/stored-value-collections/{reference}/commit' => 'post'] as $path => $method) {
            $normalize = function (array &$schema) use (&$normalize): void {
                foreach ($schema as $key => &$value) {
                    if ($key === 'properties' && is_array($value)) {
                        foreach (['amount_minor' => ['integer'], 'available_minor' => ['integer', 'null'], 'balance_after_minor' => ['integer', 'null']] as $name => $types) {
                            if (isset($value[$name])) {
                                $value[$name] = ['type' => count($types) === 1 ? $types[0] : $types];
                            }
                        }
                        if (isset($value['receipt'])) {
                            $value['receipt'] = ['type' => ['object', 'null'], 'additionalProperties' => true];
                        }
                    }
                    if (is_array($value)) {
                        $normalize($value);
                    }
                }
            };
            $normalize($baseline['paths'][$path][$method]['responses']);
        }

        $baseline['info']['version'] = '1.6.0';
        $baseline['info']['description'] = 'Partner API for account-backed direct or funded issuance and separate shared-principal funded orders.';
        $instructions = &$baseline['components']['schemas']['PayCodeInstructions'];
        $instructions['required'] = ['cash', 'inputs', 'feedback', 'rider'];
        unset($instructions['properties']['cash']['properties']['amount']['exclusiveMinimum']);
        $instructions['properties']['cash']['properties']['amount']['minimum'] = 0;
        $instructions['properties']['cash']['properties']['amount']['description'] = 'Outgoing principal in major units. Payable and explicitly supported zero-principal settlement instructions permit zero; disbursable instructions require a positive amount.';
        $instructions['properties']['voucher_type'] = ['type' => ['string', 'null'], 'description' => 'Voucher type, including payable or settlement. Omit for an ordinary disbursable Pay Code.'];
        $instructions['properties']['target_amount'] = ['type' => ['number', 'null'], 'minimum' => 0, 'description' => 'Collection target in major units; subject to the Partner mandate.'];
        $instructions['properties']['execution'] = ['type' => ['object', 'null'], 'description' => 'Versioned execution instructions. A zero-principal collect-for-settlement instruction declares voucher.execution.v2 and execution.metadata.settlement_workflow=collect_for_settlement.', 'additionalProperties' => true];
        unset($instructions);
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
            'required' => ['reference', 'checkout_reference', 'status', 'phase', 'funding_method', 'amount_minor', 'currency', 'payment_required', 'funding', 'needs_review'],
            'properties' => [
                'reference' => ['type' => 'string', 'description' => 'Order ULID used for polling.'],
                'checkout_reference' => ['type' => 'string'],
                'external_reference' => ['type' => ['string', 'null']],
                'status' => ['type' => 'string'],
                'phase' => [
                    'type' => 'string',
                    'enum' => ['payment_pending', 'payment_verified', 'funded_from_client_funds', 'issuance_pending', 'issued', 'review_required', 'cancelled', 'expired'],
                    'description' => 'Coarse order progress. An expired or review-required order must not be paid again; contact support for recovery.',
                ],
                'funding_method' => ['type' => 'string', 'enum' => ['qr_ph', 'bank_transfer']],
                'amount_minor' => ['type' => 'integer'],
                'currency' => ['type' => 'string'],
                'expires_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                'payment_required' => ['type' => 'boolean', 'description' => 'Only true while this order still accepts one exact payment.'],
                'funding' => ['type' => ['object', 'null'], 'description' => 'Selected payment instructions while payment is still accepted.'],
                'pay_code' => ['type' => ['object', 'null'], 'description' => 'Code and claim_url are present only after issuance; pay_url is available for collectible vouchers.'],
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

        $baseline['paths']['/account-funded-orders'] = [
            'post' => [
                'operationId' => 'createPartnerAccountFundedOrder',
                'summary' => 'Create an account-backed Pay Code funding order',
                'description' => 'Uses the authenticated issuer Account and reserves the external reference before funding. Disabled by default.',
                'security' => [['partnerOAuth' => ['funded-orders:create', 'pay-codes:issue']]],
                'parameters' => [[
                    'name' => 'Idempotency-Key', 'in' => 'header', 'required' => true,
                    'schema' => ['type' => 'string', 'maxLength' => 160],
                ]],
                'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/FundedOrderRequest'],
                        ['type' => 'object', 'required' => ['external_reference'], 'properties' => [
                            'external_reference' => ['type' => 'string', 'maxLength' => 190, 'pattern' => '^[A-Za-z0-9_.:-]+$'],
                        ]],
                    ],
                ]]]],
                'responses' => [
                    '201' => $response('Account-backed funding order created'),
                    '200' => $response('Idempotent replay of the existing order'),
                    '401' => ['$ref' => '#/components/responses/Unauthorized'],
                    '403' => ['$ref' => '#/components/responses/Forbidden'],
                    '404' => ['description' => 'Account-backed funding is disabled'],
                    '409' => ['description' => 'Idempotency key or external reference conflicts'],
                    '422' => ['$ref' => '#/components/responses/ValidationError'],
                    '429' => ['description' => 'Rate limit or outstanding account-funded order limit reached; retry an existing order with the same key.'],
                    '503' => ['description' => 'On-demand issuance is unavailable'],
                ],
            ],
        ];
        $baseline['paths']['/funded-orders']['post']['description'] = 'Creates an order under the shared Commercial Principal. Payable and settlement vouchers require an authenticated account-backed order instead.';

        $examples = [];
        foreach (['disbursable', 'payable', 'settlement'] as $type) {
            $instruction = [
                'cash' => ['amount' => $type === 'disbursable' ? 50 : 0, 'currency' => 'PHP', 'settlement_rail' => 'INSTAPAY', 'validation' => ['mobile' => '09171234567']],
                'inputs' => ['fields' => []], 'feedback' => ['mobile' => null], 'rider' => ['message' => null], 'count' => 1,
            ];
            if ($type !== 'disbursable') {
                $instruction['voucher_type'] = $type;
                $instruction['target_amount'] = 100;
            }
            if ($type === 'settlement') {
                $instruction['execution'] = ['schema' => 'voucher.execution.v2', 'driver' => 'default', 'metadata' => ['settlement_workflow' => 'collect_for_settlement']];
            }
            $examples[$type] = ['summary' => ucfirst($type), 'value' => $instruction];
        }
        $baseline['paths']['/pay-code-estimates']['post']['requestBody']['content']['application/json']['examples'] = $examples;
        foreach ($examples as $type => $example) {
            $example['value']['external_reference'] = 'example-'.$type.'-001';
            $baseline['paths']['/pay-codes']['post']['requestBody']['content']['application/json']['examples'][$type] = $example;
            $example['value']['funding_method'] = 'qr_ph';
            $baseline['paths']['/account-funded-orders']['post']['requestBody']['content']['application/json']['examples'][$type] = $example;
        }

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
