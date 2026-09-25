<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Settlement;

use DomainException;
use Illuminate\Contracts\Config\Repository;
use LBHurtado\SettlementEnvelope\Services\WorkflowConnectionReadiness;

/** Exact demonstration bridge; this is not a generic insurer or evidence mapper. */
final class CampaignWorkflowPublicationSnapshot
{
    public function __construct(private Repository $config, private WorkflowConnectionReadiness $readiness) {}

    /** @param array<string, mixed> $draft @param array<string, mixed> $instructions @return array<string, mixed> */
    public function fromDraft(array $draft, array $instructions): array
    {
        if (($draft['state'] ?? null) !== 'draft_only'
            || data_get($draft, 'workflow.workflow.connection') !== 'aui-demo'
            || data_get($draft, 'workflow.workflow.requires_review') !== false
            || ($draft['parameters'] ?? null) !== []) {
            throw new DomainException('This draft requires a runtime bridge not available in this gate.');
        }

        $fields = data_get($instructions, 'inputs.fields', []);
        if (! is_array($fields) || ! array_is_list($fields)
            || array_filter($fields, static fn ($field): bool => ! is_string($field)) !== []) {
            throw new DomainException('The saved template must define the completion claim fields.');
        }

        return $this->validate([
            'schema_version' => 1,
            'workflow_id' => data_get($draft, 'workflow.id'),
            'workflow_version' => data_get($draft, 'workflow.version'),
            'entry_method' => $draft['entry_method'] ?? null,
            'plan' => $draft['plan'] ?? null,
            'connection' => 'aui-demo',
            'connection_fingerprint' => $this->currentConnectionFingerprint(),
            'completion' => [
                'applicant_fields' => array_values(array_diff($fields, ['otp'])),
                'requires_otp' => in_array('otp', $fields, true),
                'prefix' => $instructions['prefix'] ?? 'POLI',
                'mask' => $instructions['mask'] ?? '****',
                'message' => data_get($instructions, 'rider.message'),
            ],
            'notifications' => data_get($draft, 'workflow.workflow.notifications', []),
            'instructions' => $instructions,
        ]);
    }

    /** @param array<string, mixed> $snapshot @return array<string, mixed> */
    public function validate(array $snapshot): array
    {
        $plan = $snapshot['plan'] ?? [];
        $completion = $snapshot['completion'] ?? [];
        $instructions = $snapshot['instructions'] ?? [];
        if (($snapshot['schema_version'] ?? null) !== 1
            || ($snapshot['workflow_id'] ?? null) !== AuiPersonalAccidentCampaignCoverageDriver::DRIVER_ID
            || ($snapshot['workflow_version'] ?? null) !== AuiPersonalAccidentCampaignCoverageDriver::DRIVER_VERSION
            || ($snapshot['entry_method'] ?? null) !== 'payment_qr'
            || ($snapshot['connection'] ?? null) !== 'aui-demo'
            || ! is_array($plan) || ! is_array($completion) || ! is_array($instructions)
            || ($plan['code'] ?? null) !== 'PA5000_DAY' || ($plan['version'] ?? null) !== '1'
            || ($plan['currency'] ?? null) !== 'PHP' || ($plan['premium_minor'] ?? null) !== 5000
            || ($plan['benefit_minor'] ?? null) !== 500000
            || data_get($plan, 'coverage.basis') !== 'day'
            || data_get($plan, 'coverage.duration_days') !== 1
            || ! is_string($plan['title'] ?? null) || trim($plan['title']) === '') {
            throw new DomainException('Only the exact AUI one-day demonstration payment QR workflow is publishable.');
        }
        if (! is_string($snapshot['connection_fingerprint'] ?? null)
            || ! hash_equals($snapshot['connection_fingerprint'], $this->currentConnectionFingerprint())) {
            throw new DomainException('The published workflow connection destination changed; publish a new reviewed revision.');
        }

        $fields = $completion['applicant_fields'] ?? null;
        $required = ['name', 'mobile', 'email', 'address', 'birth_date'];
        $allowed = [...$required, 'reference_code'];
        if (! is_array($fields) || ! array_is_list($fields)
            || array_filter($fields, static fn ($field): bool => ! is_string($field)) !== []
            || array_diff($required, $fields) !== [] || array_diff($fields, $allowed) !== []
            || count(array_unique($fields)) !== count($fields)
            || ! is_bool($completion['requires_otp'] ?? null)
            || ! is_string($completion['prefix'] ?? null) || preg_match('/\A[A-Za-z0-9-]{1,20}\z/', $completion['prefix']) !== 1
            || ! is_string($completion['mask'] ?? null) || preg_match('/\A\*{4,12}\z/', $completion['mask']) !== 1
            || (! is_null($completion['message'] ?? null) && ! is_string($completion['message']))
            || ! in_array($instructions['voucher_type'] ?? null, ['payable', 'settlement'], true)
            || ! is_numeric(data_get($instructions, 'cash.amount')) || (float) data_get($instructions, 'cash.amount') !== 0.0
            || data_get($instructions, 'cash.currency', 'PHP') !== 'PHP'
            || ! is_numeric($instructions['target_amount'] ?? null) || (float) $instructions['target_amount'] !== 50.0) {
            throw new DomainException('The template must have zero principal, a PHP 50 target, and the supported AUI personal-detail fields.');
        }

        return $snapshot;
    }

    /** @param array<string, mixed> $snapshot */
    public function hash(array $snapshot): string
    {
        return hash('sha256', json_encode($this->canonicalize($snapshot), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    /** @param array<string, mixed> $connection */
    public function connectionFingerprint(array $connection): string
    {
        return $this->hash([
            'driver' => $connection['driver'] ?? null,
            'base_url' => $connection['base_url'] ?? null,
            'auth_type' => data_get($connection, 'auth.type'),
            'connect_timeout' => $connection['connect_timeout'] ?? null,
            'timeout' => $connection['timeout'] ?? null,
        ]);
    }

    private function currentConnectionFingerprint(): string
    {
        if (! $this->readiness->check('aui-demo')->configured) {
            throw new DomainException('The AUI demonstration connection is not configured.');
        }

        return $this->connectionFingerprint($this->config->get('settlement-envelope.connections.aui-demo'));
    }

    /** @return array<mixed> */
    private function canonicalize(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->canonicalize($item);
            }
        }
        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }
}
