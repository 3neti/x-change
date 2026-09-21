<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Continuity;

use InvalidArgumentException;
use LBHurtado\XChange\Services\Keepsake\CanonicalKeepsakeJson;
use LBHurtado\XChange\Services\Treasury\ProviderStatementAttributionAudit;

final readonly class ProposeProviderStatementAttribution
{
    public function __construct(
        private ProviderStatementAttributionAudit $audit,
        private CanonicalKeepsakeJson $json,
    ) {}

    /** @return array<string, mixed> */
    public function handle(
        string $statementPath,
        string $captureManifestPath,
        string $connectionReference,
        string $expectedStatementHash,
        string $destinationInstance,
    ): array {
        $expectedStatementHash = mb_strtolower(trim($expectedStatementHash));
        $destinationInstance = trim($destinationInstance);

        if (preg_match('/^[a-f0-9]{64}$/', $expectedStatementHash) !== 1) {
            throw new InvalidArgumentException('Provide the exact 64-character statement checksum returned by capture.');
        }

        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9:._-]{2,127}$/', $destinationInstance) !== 1) {
            throw new InvalidArgumentException('Provide a stable destination instance identifier.');
        }

        $manifest = $this->captureManifest($captureManifestPath);
        $resolvedStatementPath = $this->regularFile($statementPath, 'statement');
        $actualStatementHash = hash_file('sha256', $resolvedStatementPath);

        if (! is_string($actualStatementHash)
            || ! hash_equals($expectedStatementHash, $actualStatementHash)
            || ! hash_equals($expectedStatementHash, (string) ($manifest['statement_sha256'] ?? ''))) {
            throw new InvalidArgumentException('The provider statement checksum does not match the reviewed capture evidence.');
        }

        $audit = $this->audit->audit($resolvedStatementPath, $connectionReference);
        $this->assertCaptureMatchesAudit($manifest, $audit, $resolvedStatementPath);
        $details = $this->canonicalDetails((array) ($audit['details'] ?? []));
        $counts = $this->canonicalCounts((array) ($audit['counts'] ?? []));
        $blockers = [
            'financial_apply_not_supported',
            'maker_checker_authorization_not_present',
            'provider_attribution_not_authorized',
            'transaction_ownership_not_established',
        ];

        if (($manifest['row_limit_reached'] ?? false) === true) {
            $blockers[] = 'provider_statement_sample_incomplete';
        }

        if (! $this->detailsComplete($counts, $details)) {
            $blockers[] = 'audit_detail_coverage_incomplete';
        }

        sort($blockers);
        $proposedDispositions = [
            'amount_mismatches' => 'verify_amount_against_provider_and_local_evidence',
            'duplicates' => 'retain_as_evidence_and_resolve_duplicate_source',
            'matched_inflows' => 'retain_as_verified_evidence_only',
            'matched_outflows' => 'retain_as_verified_evidence_only',
            'status_mismatches' => 'verify_final_provider_and_local_status',
            'unmatched_provider_credits' => 'establish_beneficial_owner_or_exclude',
            'unmatched_provider_debits' => 'match_prior_outflow_evidence_or_exclude',
        ];
        ksort($proposedDispositions);
        $proposal = [
            'destination_instance' => $destinationInstance,
            'connection_reference' => (string) $audit['connection_reference'],
            'provider' => (string) $audit['provider'],
            'currency' => (string) $audit['currency'],
            'statement_sha256' => $expectedStatementHash,
            'capture' => [
                'from' => (string) ($manifest['from'] ?? ''),
                'to' => (string) ($manifest['to'] ?? ''),
                'end_date_exclusive' => ($manifest['end_date_exclusive'] ?? false) === true,
                'row_count' => (int) ($manifest['row_count'] ?? -1),
                'row_limit' => (int) ($manifest['row_limit'] ?? -1),
                'row_limit_reached' => ($manifest['row_limit_reached'] ?? false) === true,
            ],
            'counts' => $counts,
            'evidence_references' => $details,
            'proposed_dispositions' => $proposedDispositions,
            'blockers' => $blockers,
        ];

        return [
            'schema' => 'x-change.provider-attribution-proposal.v1',
            'status' => 'review_required',
            'continuity_proposal_hash' => $this->json->hash($proposal),
            ...$proposal,
            'requires_maker_checker' => true,
            'apply_supported' => false,
            'read_only' => true,
            'writes_database' => false,
            'writes_storage' => false,
            'writes_cache' => false,
            'writes_journal' => false,
            'writes_treasury' => false,
            'provider_calls' => false,
            'moves_money' => false,
            'credits_accounts' => false,
            'safe_to_reconcile' => false,
            'message' => 'This deterministic proposal is evidence for review. It is not attribution, reconciliation, restoration, or credit authority.',
        ];
    }

    /** @return array<string, mixed> */
    private function captureManifest(string $path): array
    {
        $path = $this->regularFile($path, 'capture manifest');

        if (filesize($path) > 65_536) {
            throw new InvalidArgumentException('The provider statement capture manifest exceeds the supported size.');
        }

        $manifest = json_decode((string) file_get_contents($path), true);

        if (! is_array($manifest) || ($manifest['schema'] ?? null) !== 'x-change.provider-statement-snapshot.v1') {
            throw new InvalidArgumentException('The provider statement capture manifest is invalid.');
        }

        return $manifest;
    }

    private function regularFile(string $path, string $name): string
    {
        $path = trim($path);
        $resolved = $path === '' ? false : realpath($path);

        if ($resolved === false || ! is_file($resolved) || ! is_readable($resolved) || is_link($path)) {
            throw new InvalidArgumentException("The provider {$name} must be a readable regular file and may not be a symbolic link.");
        }

        return $resolved;
    }

    /** @param array<string, mixed> $manifest @param array<string, mixed> $audit */
    private function assertCaptureMatchesAudit(array $manifest, array $audit, string $statementPath): void
    {
        $from = (string) ($manifest['from'] ?? '');
        $to = (string) ($manifest['to'] ?? '');
        $rowCount = (int) ($manifest['row_count'] ?? -1);
        $rowLimit = (int) ($manifest['row_limit'] ?? -1);

        if (($manifest['status'] ?? null) !== 'captured_bounded'
            || ($manifest['connection_reference'] ?? null) !== ($audit['connection_reference'] ?? null)
            || ($manifest['provider'] ?? null) !== ($audit['provider'] ?? null)
            || ($manifest['currency'] ?? null) !== ($audit['currency'] ?? null)
            || preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) !== 1
            || preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) !== 1
            || $from >= $to
            || ($manifest['end_date_exclusive'] ?? null) !== true
            || $rowCount !== (int) ($audit['rows'] ?? -2)
            || $rowCount < 0
            || $rowLimit < 1
            || $rowCount > $rowLimit
            || (int) ($manifest['statement_bytes'] ?? -1) !== filesize($statementPath)) {
            throw new InvalidArgumentException('The capture manifest does not describe the audited provider statement.');
        }
    }

    /** @param array<string, mixed> $counts @return array<string, int> */
    private function canonicalCounts(array $counts): array
    {
        $canonical = [];

        foreach ($counts as $classification => $count) {
            $canonical[(string) $classification] = (int) $count;
        }

        ksort($canonical);

        return $canonical;
    }

    /** @param array<string, mixed> $details @return array<string, list<string>> */
    private function canonicalDetails(array $details): array
    {
        $canonical = [];

        foreach ($details as $classification => $hashes) {
            $values = array_values(array_map(
                static fn (mixed $hash): string => (string) $hash,
                is_array($hashes) ? $hashes : [],
            ));
            sort($values);
            $canonical[(string) $classification] = $values;
        }

        ksort($canonical);

        return $canonical;
    }

    /** @param array<string, int> $counts @param array<string, list<string>> $details */
    private function detailsComplete(array $counts, array $details): bool
    {
        foreach ($counts as $classification => $count) {
            if (count($details[$classification] ?? []) !== $count) {
                return false;
            }
        }

        return true;
    }
}
