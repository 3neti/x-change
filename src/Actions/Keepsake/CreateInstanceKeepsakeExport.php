<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Keepsake;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use LBHurtado\XChange\Data\Keepsake\InstanceKeepsakePlan;
use LBHurtado\XChange\Enums\DeploymentRuntimeTier;
use LBHurtado\XChange\Exceptions\InstanceKeepsakeException;
use LBHurtado\XChange\Services\Keepsake\CanonicalKeepsakeJson;
use LBHurtado\XChange\Services\Keepsake\InstanceKeepsakeArchiveWriter;
use LBHurtado\XChange\Services\Keepsake\InstanceKeepsakeGranteeFingerprint;
use LBHurtado\XChange\Services\Keepsake\InstanceKeepsakeJournal;
use Throwable;

final readonly class CreateInstanceKeepsakeExport
{
    public function __construct(
        private InstanceKeepsakeArchiveWriter $archives,
        private InstanceKeepsakeJournal $journal,
        private CanonicalKeepsakeJson $json,
        private InstanceKeepsakeGranteeFingerprint $fingerprints,
    ) {}

    /** @return array<string, mixed> */
    public function handle(
        InstanceKeepsakePlan $plan,
        string $expectedPlanHash,
        string $exportReference,
        string $authorizationReference,
        Model $downloadGrantee,
    ): array {
        $this->assertInputs($plan, $expectedPlanHash, $exportReference, $authorizationReference);
        $diskName = trim((string) config('x-change.instance_keepsake.disk', 'local'));
        $directory = trim((string) config('x-change.instance_keepsake.directory', 'x-change/instance-keepsakes'), '/');
        $publicKey = trim((string) config('x-change.instance_keepsake.public_key'));
        $this->assertReadiness($diskName, $publicKey);
        $disk = Storage::disk($diskName);
        $prefix = $directory.'/'.$exportReference;
        $archivePath = $prefix.'/instance-keepsake.xck';
        $metadataPath = $prefix.'/grant.json';

        if ($disk->exists($metadataPath)) {
            $existing = json_decode((string) $disk->get($metadataPath), true);

            if (! is_array($existing) || ! hash_equals((string) ($existing['plan_hash'] ?? ''), $plan->hash)) {
                throw new InstanceKeepsakeException('export_reference_conflict', 'The export reference already belongs to a different keepsake plan.');
            }

            if (($existing['published'] ?? false) !== true || ! $disk->exists((string) ($existing['archive_path'] ?? ''))) {
                throw new InstanceKeepsakeException('export_incomplete', 'The existing keepsake export did not complete publication safely.');
            }

            return $this->result($existing, true);
        }

        $archive = $this->archives->write($plan, $publicKey);
        $stream = fopen($archive['encrypted_path'], 'rb');

        if (! is_resource($stream)) {
            $this->archives->cleanup($archive['encrypted_path']);
            throw new InstanceKeepsakeException('storage_unavailable', 'The encrypted keepsake could not be staged for upload.');
        }

        try {
            if (! $disk->put($archivePath, $stream, ['visibility' => 'private'])) {
                throw new InstanceKeepsakeException('storage_unavailable', 'The encrypted keepsake could not be uploaded.');
            }
        } finally {
            fclose($stream);
            $this->archives->cleanup($archive['encrypted_path']);
        }

        $createdAt = CarbonImmutable::now('UTC');
        $metadata = [
            'schema' => 'x-change.instance-keepsake-grant.v1',
            'export_reference' => $exportReference,
            'plan_hash' => $plan->hash,
            'manifest_sha256' => $archive['manifest_sha256'],
            'archive_sha256' => $archive['archive_sha256'],
            'archive_path' => $archivePath,
            'archive_filename' => $exportReference.'.xck',
            'disk' => $diskName,
            'entry_count' => $archive['entry_count'],
            'artifact_count' => $plan->artifactCount,
            'artifact_bytes' => $plan->artifactBytes,
            'omission_count' => $plan->omissionCount,
            'complete' => $plan->omissionCount === 0,
            'grantee_type' => $downloadGrantee->getMorphClass(),
            'grantee_id' => (string) $downloadGrantee->getKey(),
            'grantee_fingerprint' => $this->fingerprints->for($downloadGrantee),
            'created_at' => $createdAt->toIso8601String(),
            'expires_at' => $createdAt->addMinutes(
                (int) config('x-change.instance_keepsake.download_ttl_minutes', 30),
            )->toIso8601String(),
            'consumed_at' => null,
            'published' => false,
        ];

        try {
            if (! $disk->put($metadataPath, $this->json->encode($metadata), ['visibility' => 'private'])) {
                throw new InstanceKeepsakeException('storage_unavailable', 'The keepsake download grant could not be staged.');
            }

            $this->journal->exported(
                exportReference: $exportReference,
                authorizationReference: $authorizationReference,
                planHash: $plan->hash,
                manifestHash: $archive['manifest_sha256'],
                archiveHash: $archive['archive_sha256'],
                counts: [
                    'users' => $plan->userCount,
                    'pay_codes' => $plan->payCodeCount,
                    'artifacts' => $plan->artifactCount,
                    'omissions' => $plan->omissionCount,
                ],
            );
            $metadata['published'] = true;
            if (! $disk->put($metadataPath, $this->json->encode($metadata), ['visibility' => 'private'])) {
                throw new InstanceKeepsakeException('storage_unavailable', 'The keepsake download grant could not be published.');
            }
        } catch (Throwable $exception) {
            $disk->delete([$archivePath, $metadataPath]);
            throw $exception;
        }

        return $this->result($metadata, false);
    }

    private function assertInputs(
        InstanceKeepsakePlan $plan,
        string $expectedPlanHash,
        string $exportReference,
        string $authorizationReference,
    ): void {
        if (! hash_equals($plan->hash, trim($expectedPlanHash))) {
            throw new InstanceKeepsakeException('plan_stale', 'The keepsake plan changed after preview. Run the dry-run again.');
        }

        if (preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._-]{5,95}$/', $exportReference) !== 1) {
            throw new InstanceKeepsakeException('invalid_export_reference', 'Provide a safe export reference between 6 and 96 characters.');
        }

        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9:._\/-]{5,127}$/', $authorizationReference) !== 1) {
            throw new InstanceKeepsakeException('authorization_required', 'Provide a safe external authorization reference between 6 and 128 characters.');
        }
    }

    private function assertReadiness(string $disk, string $publicKey): void
    {
        if ($disk === ''
            || $disk === 'public'
            || (! app()->environment('testing') && config("filesystems.disks.{$disk}") === null)) {
            throw new InstanceKeepsakeException('storage_unavailable', 'The private keepsake disk is not configured.');
        }

        $driver = (string) config("filesystems.disks.{$disk}.driver");
        $runtimeTier = DeploymentRuntimeTier::resolve((string) config(
            'x-change.deployment.runtime_tier',
            DeploymentRuntimeTier::Local->value,
        ));

        if ($runtimeTier->requiresDurableInfrastructure() && ($disk === 'local' || $driver === 'local')) {
            throw new InstanceKeepsakeException('storage_unavailable', 'Staging and production keepsakes require a durable non-local private disk.');
        }

        if (! app()->environment('testing') && $driver === '') {
            throw new InstanceKeepsakeException('storage_unavailable', 'The private keepsake disk driver is not configured.');
        }

        if ($runtimeTier->requiresDurableInfrastructure() && $driver === 's3') {
            foreach (['key', 'secret', 'bucket'] as $configurationKey) {
                if (blank(config("filesystems.disks.{$disk}.{$configurationKey}"))) {
                    throw new InstanceKeepsakeException('storage_unavailable', 'The durable private keepsake disk is incomplete.');
                }
            }
        }

        if ($publicKey === '') {
            throw new InstanceKeepsakeException('encryption_unavailable', 'Configure the keepsake recipient public key before creation.');
        }
    }

    /** @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    private function result(array $metadata, bool $replayed): array
    {
        return [
            'schema' => 'x-change.instance-keepsake-export.v1',
            'status' => ($metadata['complete'] ?? false)
                ? 'ready_for_authenticated_download'
                : 'review_required',
            'created' => ! $replayed,
            'replayed' => $replayed,
            'export_reference' => $metadata['export_reference'],
            'plan_hash' => $metadata['plan_hash'],
            'manifest_sha256' => $metadata['manifest_sha256'],
            'archive_sha256' => $metadata['archive_sha256'],
            'entry_count' => $metadata['entry_count'],
            'artifact_count' => $metadata['artifact_count'],
            'artifact_bytes' => $metadata['artifact_bytes'],
            'omission_count' => $metadata['omission_count'],
            'complete' => $metadata['complete'],
            'expires_at' => $metadata['expires_at'],
            'download_path' => route(
                'x-change.cockpit.instance-keepsakes.download',
                ['reference' => $metadata['export_reference']],
                absolute: false,
            ),
            'encrypted' => true,
            'provider_calls' => false,
            'moves_money' => false,
            'restores_financial_state' => false,
            'migrate_fresh_invoked' => false,
        ];
    }
}
