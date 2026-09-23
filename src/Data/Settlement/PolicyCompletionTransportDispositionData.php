<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Settlement;

use Carbon\CarbonImmutable;
use DomainException;
use SensitiveParameter;

final readonly class PolicyCompletionTransportDispositionData
{
    public const SCHEMA = 'x-change.policy-completion-transport-disposition.v1';

    /** @var list<string> */
    public const REQUIRED_FIELDS = [
        'schema',
        'enabled',
        'accepted',
        'driver_id',
        'driver_version',
        'provider',
        'contract_id',
        'contract_version',
        'submission_endpoint',
        'authentication_scheme',
        'request_schema_reference',
        'request_schema_version',
        'request_schema_digest',
        'response_schema_reference',
        'response_schema_version',
        'response_schema_digest',
        'idempotency_mechanism',
        'connect_timeout_seconds',
        'response_timeout_seconds',
        'retry_policy',
        'ambiguous_outcome_policy',
        'reconciliation_mode',
        'credential_reference',
        'acceptance_reference',
        'accepted_at',
        'accepted_by_reference',
    ];

    private function __construct(
        public string $driverId,
        public string $driverVersion,
        private array $normalized,
        #[SensitiveParameter]
        private string $credentialReference,
    ) {}

    /**
     * @param  array<string, mixed>  $manifest
     */
    public static function fromManifest(
        array $manifest,
        string $expectedDriverId,
        string $expectedDriverVersion,
    ): self {
        $unknown = array_values(array_diff(array_keys($manifest), self::REQUIRED_FIELDS));
        if ($unknown !== []) {
            throw new DomainException('Policy completion transport disposition contains undeclared fields.');
        }

        $missing = array_values(array_filter(
            self::REQUIRED_FIELDS,
            static fn (string $field): bool => ! array_key_exists($field, $manifest),
        ));
        if ($missing !== []) {
            throw new DomainException('Policy completion transport disposition is incomplete.');
        }

        if (($manifest['schema'] ?? null) !== self::SCHEMA
            || ($manifest['enabled'] ?? null) !== true
            || ($manifest['accepted'] ?? null) !== true) {
            throw new DomainException('Policy completion transport disposition is not accepted and enabled.');
        }

        $strings = [];
        foreach (array_diff(self::REQUIRED_FIELDS, [
            'enabled',
            'accepted',
            'connect_timeout_seconds',
            'response_timeout_seconds',
        ]) as $field) {
            $value = $manifest[$field] ?? null;
            if (! is_string($value) || trim($value) === '') {
                throw new DomainException('Policy completion transport disposition contains an invalid string field.');
            }
            $strings[$field] = trim($value);
        }

        if ($strings['driver_id'] !== $expectedDriverId
            || $strings['driver_version'] !== $expectedDriverVersion) {
            throw new DomainException('Policy completion transport disposition identity does not match the request.');
        }

        $endpoint = parse_url($strings['submission_endpoint']);
        if (! is_array($endpoint)
            || ($endpoint['scheme'] ?? null) !== 'https'
            || ! is_string($endpoint['host'] ?? null)
            || trim($endpoint['host']) === '') {
            throw new DomainException('Policy completion transport disposition requires an HTTPS submission endpoint.');
        }

        foreach (['request_schema_digest', 'response_schema_digest'] as $field) {
            if (preg_match('/\Asha256:[a-f0-9]{64}\z/', $strings[$field]) !== 1) {
                throw new DomainException('Policy completion transport schema digest is invalid.');
            }
        }

        if (! str_starts_with($strings['credential_reference'], 'services.')
            && ! str_starts_with($strings['credential_reference'], 'x-change.integrations.')) {
            throw new DomainException('Policy completion transport credential reference is outside the approved configuration namespaces.');
        }

        $connectTimeout = $manifest['connect_timeout_seconds'] ?? null;
        $responseTimeout = $manifest['response_timeout_seconds'] ?? null;
        if (! is_int($connectTimeout)
            || ! is_int($responseTimeout)
            || $connectTimeout < 1
            || $responseTimeout < $connectTimeout) {
            throw new DomainException('Policy completion transport timeouts are invalid.');
        }

        if (preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})\z/', $strings['accepted_at']) !== 1) {
            throw new DomainException('Policy completion transport acceptance timestamp is invalid.');
        }

        try {
            $acceptedAt = CarbonImmutable::parse($strings['accepted_at'])->utc();
        } catch (\Throwable) {
            throw new DomainException('Policy completion transport acceptance timestamp is invalid.');
        }

        $normalized = [
            ...$strings,
            'schema' => self::SCHEMA,
            'enabled' => true,
            'accepted' => true,
            'connect_timeout_seconds' => $connectTimeout,
            'response_timeout_seconds' => $responseTimeout,
            'accepted_at' => $acceptedAt->toIso8601String(),
        ];
        ksort($normalized);

        return new self(
            driverId: $strings['driver_id'],
            driverVersion: $strings['driver_version'],
            normalized: $normalized,
            credentialReference: $strings['credential_reference'],
        );
    }

    public function credentialReference(): string
    {
        return $this->credentialReference;
    }

    public function fingerprint(): string
    {
        return hash('sha256', json_encode($this->normalized, JSON_THROW_ON_ERROR));
    }
}
