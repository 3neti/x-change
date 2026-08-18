<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Keepsake\Contributors;

use LBHurtado\ModelInput\Models\Input;
use LBHurtado\XChange\Contracts\Keepsake\InstanceKeepsakeContributor;
use LBHurtado\XChange\Data\Keepsake\InstanceKeepsakeContext;
use LBHurtado\XChange\Data\Keepsake\InstanceKeepsakeContribution;
use LBHurtado\XChange\Exceptions\InstanceKeepsakeException;
use LBHurtado\XChange\Models\VoucherClaimEvidence;
use LBHurtado\XChange\Services\Claim\ClaimEvidenceArtifactReader;
use LBHurtado\XChange\Services\Keepsake\CanonicalKeepsakeJson;

final readonly class ClaimEvidenceKeepsakeContributor implements InstanceKeepsakeContributor
{
    private const REQUIREMENTS = ['selfie', 'signature', 'location'];

    public function __construct(
        private ClaimEvidenceArtifactReader $reader,
        private CanonicalKeepsakeJson $json,
    ) {}

    public function key(): string
    {
        return 'claim-evidence';
    }

    public function snapshotSchemaVersion(): int
    {
        return 1;
    }

    public function blueprintSchemaVersion(): ?int
    {
        return null;
    }

    public function contribute(InstanceKeepsakeContext $context): InstanceKeepsakeContribution
    {
        if (! $context->includes('claim-evidence')) {
            return new InstanceKeepsakeContribution($this->key(), 1, null);
        }

        $voucherIds = [];
        $voucherReferences = [];

        foreach ($context->vouchers as $voucher) {
            $id = (string) $voucher['model']->getKey();
            $voucherIds[] = $voucher['model']->getKey();
            $voucherReferences[$id] = $voucher['reference'];
        }

        if ($voucherIds === []) {
            return $this->emptyContribution();
        }

        $artifacts = [];
        $records = [];
        $sidecars = [];
        $omissions = [];
        $modern = [];
        $claims = [];

        VoucherClaimEvidence::query()
            ->with('claim:id,claim_number')
            ->whereIn('voucher_id', $voucherIds)
            ->whereIn('requirement_key', self::REQUIREMENTS)
            ->orderBy('id')
            ->lazyById((int) config('x-change.instance_keepsake.chunk_size', 100))
            ->each(function (VoucherClaimEvidence $evidence) use (
                $context,
                $voucherReferences,
                &$artifacts,
                &$records,
                &$sidecars,
                &$omissions,
                &$modern,
                &$claims,
            ): void {
                $voucherReference = $voucherReferences[(string) $evidence->voucher_id] ?? null;

                if ($voucherReference === null) {
                    return;
                }

                $claimKey = (string) $evidence->voucher_claim_id;
                $claims[$claimKey] ??= 'claim-'.str_pad((string) (count($claims) + 1), 6, '0', STR_PAD_LEFT);
                $claimReference = $claims[$claimKey];
                $modern[$evidence->voucher_id.'|'.$evidence->requirement_key] = true;

                try {
                    $artifact = $this->reader->stored($evidence);
                    $this->assertItemLimit($artifact['size']);
                    $path = $this->artifactPath(
                        $voucherReference,
                        $claimReference,
                        (string) $evidence->requirement_key,
                        $artifact['extension'],
                    );

                    $artifacts[] = $this->artifact(
                        $artifact,
                        $path,
                        'durable',
                        $context->materializeArtifacts,
                    );
                    $records[] = $this->record(
                        $path,
                        (string) $evidence->requirement_key,
                        $artifact,
                        'durable',
                        $evidence->captured_at?->toIso8601String(),
                    );

                    if ($context->includeLocationData && $evidence->requirement_key === 'location') {
                        $payload = is_array($evidence->payload) ? $evidence->payload : [];
                        unset($payload['map']);
                        $sidecars[dirname($path).'/location.json'] = $this->json->encode([
                            'schema' => 'x-change.instance-keepsake.location.v1',
                            'sensitive' => true,
                            'location' => $payload,
                        ]);
                    }
                } catch (InstanceKeepsakeException $exception) {
                    $this->handleFailure($context, $omissions, $exception, $voucherReference, (string) $evidence->requirement_key);
                }
            });

        $voucherModel = $context->vouchers[0]['model'];
        $legacySeen = [];

        Input::query()
            ->where('model_type', $voucherModel->getMorphClass())
            ->whereIn('model_id', $voucherIds)
            ->whereIn('name', self::REQUIREMENTS)
            ->orderByDesc('id')
            ->lazyByIdDesc((int) config('x-change.instance_keepsake.chunk_size', 100))
            ->each(function (Input $input) use (
                $context,
                $voucherReferences,
                $modern,
                &$legacySeen,
                &$artifacts,
                &$records,
                &$sidecars,
                &$omissions,
            ): void {
                $key = $input->model_id.'|'.$input->name;

                if (isset($modern[$key]) || isset($legacySeen[$key])) {
                    return;
                }

                $legacySeen[$key] = true;
                $value = (string) $input->value;

                if ($this->reader->isStoredPointer($value)) {
                    return;
                }

                $voucherReference = $voucherReferences[(string) $input->model_id] ?? null;

                if ($voucherReference === null) {
                    return;
                }

                try {
                    $artifact = $this->reader->legacy($value, (string) $input->name);
                    $this->assertItemLimit($artifact['size']);
                    $path = $this->artifactPath(
                        $voucherReference,
                        'legacy',
                        (string) $input->name,
                        $artifact['extension'],
                    );

                    $artifacts[] = $this->artifact(
                        $artifact,
                        $path,
                        'legacy',
                        $context->materializeArtifacts,
                    );
                    $records[] = $this->record(
                        $path,
                        (string) $input->name,
                        $artifact,
                        'legacy',
                        $input->created_at?->toIso8601String(),
                    );

                    if ($context->includeLocationData && $input->name === 'location') {
                        $location = json_decode($value, true);

                        if (is_array($location)) {
                            unset($location['map']);
                            $sidecars[dirname($path).'/location.json'] = $this->json->encode([
                                'schema' => 'x-change.instance-keepsake.location.v1',
                                'sensitive' => true,
                                'location' => $location,
                            ]);
                        }
                    }
                } catch (InstanceKeepsakeException $exception) {
                    $this->handleFailure($context, $omissions, $exception, $voucherReference, (string) $input->name);
                }
            });

        usort($records, static fn (array $left, array $right): int => $left['archive_path'] <=> $right['archive_path']);

        return new InstanceKeepsakeContribution(
            key: $this->key(),
            snapshotSchemaVersion: 1,
            blueprintSchemaVersion: null,
            snapshotFiles: [
                'snapshot/claim-evidence.json' => $this->json->encode([
                    'schema' => 'x-change.instance-keepsake.claim-evidence.v1',
                    'blueprint_eligible' => false,
                    'records' => $records,
                    'omissions' => $omissions,
                ]),
                ...$sidecars,
            ],
            artifacts: $artifacts,
            summary: [
                'artifacts' => count($artifacts),
                'artifact_bytes' => array_sum(array_column($artifacts, 'size')),
                'omissions' => count($omissions),
            ],
            omissions: $omissions,
        );
    }

    private function emptyContribution(): InstanceKeepsakeContribution
    {
        return new InstanceKeepsakeContribution(
            key: $this->key(),
            snapshotSchemaVersion: 1,
            blueprintSchemaVersion: null,
            snapshotFiles: [
                'snapshot/claim-evidence.json' => $this->json->encode([
                    'schema' => 'x-change.instance-keepsake.claim-evidence.v1',
                    'blueprint_eligible' => false,
                    'records' => [],
                    'omissions' => [],
                ]),
            ],
            summary: ['artifacts' => 0, 'artifact_bytes' => 0, 'omissions' => 0],
        );
    }

    /** @param array{contents:string,mime_type:string,extension:string,size:int,sha256:string} $artifact */
    private function artifact(array $artifact, string $path, string $source, bool $materialize): array
    {
        $entry = [
            'path' => $path,
            'mime_type' => $artifact['mime_type'],
            'size' => $artifact['size'],
            'sha256' => $artifact['sha256'],
            'source' => $source,
        ];

        if ($materialize) {
            $entry['contents'] = $artifact['contents'];
        }

        return $entry;
    }

    /** @param array{mime_type:string,size:int,sha256:string} $artifact */
    private function record(
        string $path,
        string $requirement,
        array $artifact,
        string $source,
        ?string $capturedAt,
    ): array {
        return [
            'requirement' => $requirement,
            'source' => $source,
            'archive_path' => $path,
            'mime_type' => $artifact['mime_type'],
            'size' => $artifact['size'],
            'sha256' => $artifact['sha256'],
            'captured_at' => $capturedAt,
        ];
    }

    private function artifactPath(string $voucher, string $claim, string $requirement, string $extension): string
    {
        return "snapshot/evidence/{$voucher}/{$claim}/{$requirement}.{$extension}";
    }

    private function assertItemLimit(int $size): void
    {
        if ($size > (int) config('x-change.instance_keepsake.max_item_bytes', 10 * 1024 * 1024)) {
            throw new InstanceKeepsakeException('limit_exceeded', 'An evidence artifact exceeds the configured item-size limit.');
        }
    }

    /** @param list<array{reason:string,reference:string}> $omissions */
    private function handleFailure(
        InstanceKeepsakeContext $context,
        array &$omissions,
        InstanceKeepsakeException $exception,
        string $voucherReference,
        string $requirement,
    ): void {
        if (! $context->allowIncomplete) {
            throw $exception;
        }

        $omissions[] = [
            'reason' => $exception->reason,
            'reference' => $voucherReference.':'.$requirement,
        ];
    }
}
