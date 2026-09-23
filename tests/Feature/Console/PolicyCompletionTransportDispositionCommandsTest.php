<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use LBHurtado\XChange\Services\Settlement\AuiPersonalAccidentPolicyCompletionDriver;
use Symfony\Component\Yaml\Yaml;

it('generates a disabled secret-free disposition template without overwriting files', function (): void {
    $path = sys_get_temp_dir().'/policy-completion-template-'.str()->uuid().'.yaml';
    Http::preventStrayRequests();

    try {
        $arguments = [
            'driver' => AuiPersonalAccidentPolicyCompletionDriver::DRIVER_ID,
            'version' => AuiPersonalAccidentPolicyCompletionDriver::DRIVER_VERSION,
            '--path' => $path,
            '--json' => true,
        ];
        $firstExitCode = Artisan::call('x-change:policy-completion-transport:template', $arguments);
        $firstOutput = Artisan::output();
        $manifest = Yaml::parseFile($path);
        $secondExitCode = Artisan::call('x-change:policy-completion-transport:template', $arguments);

        expect($firstExitCode)->toBe(0)
            ->and($firstOutput)->toContain('"success": true')
            ->and($manifest)->toMatchArray([
                'schema' => 'x-change.policy-completion-transport-disposition.v1',
                'enabled' => false,
                'accepted' => false,
                'driver_id' => AuiPersonalAccidentPolicyCompletionDriver::DRIVER_ID,
                'driver_version' => AuiPersonalAccidentPolicyCompletionDriver::DRIVER_VERSION,
            ])
            ->and($manifest)->not->toHaveKey('credential_value')
            ->and(file_get_contents($path))->not->toContain('secret', 'token')
            ->and($secondExitCode)->toBe(1);
    } finally {
        @unlink($path);
    }

    Http::assertNothingSent();
});

it('validates an accepted local disposition with redacted deterministic output', function (): void {
    $path = sys_get_temp_dir().'/policy-completion-valid-'.str()->uuid().'.yaml';
    $manifest = validPolicyCompletionDispositionManifest();
    file_put_contents($path, Yaml::dump($manifest, 4, 2));
    Http::preventStrayRequests();

    try {
        $arguments = [
            'driver' => AuiPersonalAccidentPolicyCompletionDriver::DRIVER_ID,
            'version' => AuiPersonalAccidentPolicyCompletionDriver::DRIVER_VERSION,
            '--path' => $path,
            '--json' => true,
        ];
        $firstExitCode = Artisan::call('x-change:policy-completion-transport:validate', $arguments);
        $firstOutput = Artisan::output();
        file_put_contents($path, Yaml::dump(array_reverse($manifest, true), 4, 2));
        $secondExitCode = Artisan::call('x-change:policy-completion-transport:validate', $arguments);
        $secondOutput = Artisan::output();
        $first = json_decode($firstOutput, true, flags: JSON_THROW_ON_ERROR);
        $second = json_decode($secondOutput, true, flags: JSON_THROW_ON_ERROR);

        expect($firstExitCode)->toBe(0)
            ->and($secondExitCode)->toBe(0)
            ->and($first['disposition_fingerprint'])->toMatch('/^[a-f0-9]{64}$/')
            ->and($second['disposition_fingerprint'])->toBe($first['disposition_fingerprint'])
            ->and($firstOutput)->not->toContain(
                'policy-provider.example.test',
                'services.aui.policy_completion_token',
                'architecture-approval-001',
            );
    } finally {
        @unlink($path);
    }

    Http::assertNothingSent();
});

it('rejects invalid and remote disposition input without leaking manifest values', function (): void {
    $path = sys_get_temp_dir().'/policy-completion-invalid-'.str()->uuid().'.yaml';
    file_put_contents($path, Yaml::dump([
        ...validPolicyCompletionDispositionManifest(),
        'credential_value' => 'do-not-leak-this-secret',
    ], 4, 2));
    Http::preventStrayRequests();

    try {
        $arguments = [
            'driver' => AuiPersonalAccidentPolicyCompletionDriver::DRIVER_ID,
            'version' => AuiPersonalAccidentPolicyCompletionDriver::DRIVER_VERSION,
            '--path' => $path,
            '--json' => true,
        ];
        $exitCode = Artisan::call('x-change:policy-completion-transport:validate', $arguments);
        $output = Artisan::output();
        file_put_contents($path, "- not\n- a\n- mapping\n");
        $nonMappingExitCode = Artisan::call('x-change:policy-completion-transport:validate', $arguments);
        $remoteExitCode = Artisan::call('x-change:policy-completion-transport:validate', [
            ...$arguments,
            '--path' => 'https://provider.example.test/disposition.yaml',
        ]);

        expect($exitCode)->toBe(1)
            ->and($output)->toContain('"success": false')
            ->and($output)->not->toContain('do-not-leak-this-secret')
            ->and($nonMappingExitCode)->toBe(1)
            ->and($remoteExitCode)->toBe(1);
    } finally {
        @unlink($path);
    }

    Http::assertNothingSent();
});

/** @return array<string, bool|int|string> */
function validPolicyCompletionDispositionManifest(): array
{
    return [
        'schema' => 'x-change.policy-completion-transport-disposition.v1',
        'enabled' => true,
        'accepted' => true,
        'driver_id' => AuiPersonalAccidentPolicyCompletionDriver::DRIVER_ID,
        'driver_version' => AuiPersonalAccidentPolicyCompletionDriver::DRIVER_VERSION,
        'provider' => 'synthetic-test-provider',
        'contract_id' => 'synthetic-policy-completion',
        'contract_version' => '1.0',
        'submission_endpoint' => 'https://policy-provider.example.test/v1/completions',
        'authentication_scheme' => 'bearer-token',
        'request_schema_reference' => 'test://policy-completion-request',
        'request_schema_version' => '1.0',
        'request_schema_digest' => 'sha256:'.str_repeat('a', 64),
        'response_schema_reference' => 'test://policy-completion-response',
        'response_schema_version' => '1.0',
        'response_schema_digest' => 'sha256:'.str_repeat('b', 64),
        'idempotency_mechanism' => 'provider-request-key',
        'connect_timeout_seconds' => 5,
        'response_timeout_seconds' => 15,
        'retry_policy' => 'selective-transient-only',
        'ambiguous_outcome_policy' => 'record-indeterminate-and-reconcile',
        'reconciliation_mode' => 'provider-status-query',
        'credential_reference' => 'services.aui.policy_completion_token',
        'acceptance_reference' => 'architecture-approval-001',
        'accepted_at' => '2026-09-23T00:00:00+00:00',
        'accepted_by_reference' => 'test-architecture-authority',
    ];
}
