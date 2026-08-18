<?php

declare(strict_types=1);

use LBHurtado\XChange\Exceptions\InstanceKeepsakeException;
use LBHurtado\XChange\Services\Keepsake\InstanceKeepsakeSchemaValidator;

it('accepts an inert account invitation blueprint', function () {
    $contents = json_encode([
        'schema' => 'x-change.instance-keepsake.account-invitations.v1',
        'inert' => true,
        'importer_included' => false,
        'invitations' => [[
            'reference' => 'account-000001',
            'profile' => ['name' => 'Keepsake User', 'email' => null, 'mobile' => null],
            'desired_state' => 'pending',
            'enabled' => false,
            'requires_reverification' => true,
            'credentials_included' => false,
            'authority_included' => false,
            'financial_state_included' => false,
        ]],
    ], JSON_THROW_ON_ERROR);

    expect(fn () => app(InstanceKeepsakeSchemaValidator::class)->validate(
        'blueprint/account-invitations.json',
        $contents,
    ))->not->toThrow(InstanceKeepsakeException::class);
});

it('rejects unknown blueprint fields', function () {
    $contents = json_encode([
        'schema' => 'x-change.instance-keepsake.pay-code-templates.v1',
        'inert' => true,
        'original_codes_included' => false,
        'templates' => [[
            'reference' => 'template-000001',
            'account_reference' => 'account-000001',
            'name' => 'Safe template',
            'description' => null,
            'base_template_key' => 'scratch',
            'include_amount' => true,
            'include_purpose' => true,
            'instructions_included' => false,
            'requires_review' => true,
            'unexpected' => 'not allowed',
        ]],
    ], JSON_THROW_ON_ERROR);

    expect(fn () => app(InstanceKeepsakeSchemaValidator::class)->validate(
        'blueprint/pay-code-templates.json',
        $contents,
    ))->toThrow(InstanceKeepsakeException::class);
});

it('rejects financial state in a bootstrap blueprint', function () {
    $contents = json_encode([
        'schema' => 'x-change.instance-keepsake.account-invitations.v1',
        'inert' => true,
        'importer_included' => false,
        'invitations' => [['client_funds_minor' => 50000]],
    ], JSON_THROW_ON_ERROR);

    expect(fn () => app(InstanceKeepsakeSchemaValidator::class)->validate(
        'blueprint/account-invitations.json',
        $contents,
    ))->toThrow(InstanceKeepsakeException::class, 'forbidden field');
});

it('rejects a raw Pay Code bearer credential', function () {
    $contents = json_encode([
        'schema' => 'x-change.instance-keepsake.pay-codes.v1',
        'issued_codes_are_historical_only' => true,
        'pay_codes' => [[
            'reference' => 'pay-code-000001',
            'account_reference' => 'account-000001',
            'code' => 'ABCD',
            'state' => 'active',
            'amount_minor' => 100,
            'currency' => 'PHP',
            'created_at' => null,
            'expires_at' => null,
            'redeemed_at' => null,
            'historical_only' => true,
            'restorable' => false,
        ]],
    ], JSON_THROW_ON_ERROR);

    expect(fn () => app(InstanceKeepsakeSchemaValidator::class)->validate(
        'snapshot/pay-codes.json',
        $contents,
    ))->toThrow(InstanceKeepsakeException::class);
});

it('ships parseable versioned JSON schemas', function () {
    $schemas = glob(dirname(__DIR__, 4).'/resources/schemas/instance-keepsake/*.json') ?: [];

    expect($schemas)->not->toBeEmpty();

    foreach ($schemas as $schema) {
        expect(json_decode((string) file_get_contents($schema), true, flags: JSON_THROW_ON_ERROR))
            ->toBeArray()
            ->toHaveKey('$schema');
    }
});
