<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Keepsake;

use LBHurtado\XChange\Exceptions\InstanceKeepsakeException;
use Throwable;

final readonly class InspectInstanceKeepsakeArchive
{
    public function __construct(private VerifyInstanceKeepsakeArchive $verifier) {}

    /** @return array<string, mixed> */
    public function handle(string $archivePath, string $keyPath, string $expectedArchiveHash): array
    {
        $workspace = sys_get_temp_dir().'/x-change-keepsake-inspect-'.bin2hex(random_bytes(12));

        try {
            $verification = $this->verifier->handle(
                archivePath: $archivePath,
                keyPath: $keyPath,
                expectedArchiveHash: $expectedArchiveHash,
                extractTo: $workspace,
            );
            $manifest = $this->readJson($workspace.'/manifest.json');
            $accounts = $this->readOptionalJson($workspace.'/snapshot/accounts.json');
            $payCodes = $this->readOptionalJson($workspace.'/snapshot/pay-codes.json');
            $evidence = $this->readOptionalJson($workspace.'/snapshot/claim-evidence.json');
            $accountInvitations = $this->readOptionalJson($workspace.'/blueprint/account-invitations.json');
            $templates = $this->readOptionalJson($workspace.'/blueprint/pay-code-templates.json');
            $accountRows = $this->list($accounts, 'accounts');
            $payCodeRows = $this->list($payCodes, 'pay_codes');
            $evidenceRows = $this->list($evidence, 'records');

            return [
                'schema' => 'x-change.instance-keepsake-inspection.v1',
                'status' => 'verified',
                'archive_sha256' => $verification['archive_sha256'],
                'manifest_sha256' => $verification['manifest_sha256'],
                'plan_hash' => $verification['plan_hash'],
                'observed_at' => $manifest['observed_at'] ?? null,
                'created_at' => $manifest['created_at'] ?? null,
                'package_version' => $manifest['package_version'] ?? null,
                'complete' => $verification['complete'],
                'entry_count' => $verification['entry_count'],
                'inventory' => [
                    'accounts' => count($accountRows),
                    'pay_codes' => count($payCodeRows),
                    'claim_evidence_records' => count($evidenceRows),
                    'account_invitations' => count($this->list($accountInvitations, 'invitations')),
                    'pay_code_templates' => count($this->list($templates, 'templates')),
                    'artifact_entries' => count(array_filter(
                        $manifest['entries'] ?? [],
                        static fn (mixed $entry): bool => is_array($entry)
                            && str_starts_with((string) ($entry['path'] ?? ''), 'artifacts/'),
                    )),
                ],
                'financial_observation' => $this->financialObservation($accountRows),
                'pay_code_states' => $this->payCodeStates($payCodeRows),
                'privacy' => [
                    'personal_data_present' => $this->personalDataPresent($accountRows),
                    'precise_location_sidecars' => count(array_filter(
                        $manifest['entries'] ?? [],
                        static fn (mixed $entry): bool => is_array($entry)
                            && str_ends_with((string) ($entry['path'] ?? ''), '/location.json'),
                    )),
                ],
                'capabilities' => [
                    'evidence_review' => true,
                    'financial_observation' => $accountRows !== [],
                    'blueprint_review' => $accountInvitations !== null || $templates !== null,
                    'restores_live_pay_codes' => false,
                    'applies_financial_state' => false,
                ],
                'read_only' => true,
                'writes_database' => false,
                'provider_calls' => false,
                'moves_money' => false,
                'safe_to_reset' => false,
            ];
        } catch (Throwable $exception) {
            if (! $exception instanceof InstanceKeepsakeException) {
                throw new InstanceKeepsakeException('inspection_failed', 'The verified keepsake could not be inspected safely.');
            }

            throw $exception;
        } finally {
            $this->removeDirectory($workspace);
        }
    }

    /** @return array<string, mixed> */
    private function readJson(string $path): array
    {
        $contents = is_file($path) ? file_get_contents($path) : false;

        if (! is_string($contents)) {
            throw new InstanceKeepsakeException('inspection_failed', 'A required keepsake document is missing.');
        }

        $decoded = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw new InstanceKeepsakeException('inspection_failed', 'A keepsake document is not an object.');
        }

        return $decoded;
    }

    /** @return array<string, mixed>|null */
    private function readOptionalJson(string $path): ?array
    {
        return is_file($path) ? $this->readJson($path) : null;
    }

    /** @param array<string, mixed>|null $document
     * @return list<array<string, mixed>>
     */
    private function list(?array $document, string $key): array
    {
        $rows = $document[$key] ?? [];

        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    /** @param list<array<string, mixed>> $accounts
     * @return array<string, int|null>
     */
    private function financialObservation(array $accounts): array
    {
        $clientFunds = 0;
        $outstanding = 0;
        $issuance = 0;
        $clientFundsComplete = true;
        $outstandingComplete = true;
        $issuanceComplete = true;

        foreach ($accounts as $account) {
            $clientFundsComplete = $clientFundsComplete && is_int($account['client_funds_minor'] ?? null);
            $outstandingComplete = $outstandingComplete && is_int($account['outstanding_pay_codes_minor'] ?? null);
            $issuanceComplete = $issuanceComplete && is_int($account['issuance_capacity_minor'] ?? null);
            $clientFunds += is_int($account['client_funds_minor'] ?? null) ? $account['client_funds_minor'] : 0;
            $outstanding += is_int($account['outstanding_pay_codes_minor'] ?? null) ? $account['outstanding_pay_codes_minor'] : 0;
            $issuance += is_int($account['issuance_capacity_minor'] ?? null) ? $account['issuance_capacity_minor'] : 0;
        }

        return [
            'client_funds_minor' => $clientFundsComplete ? $clientFunds : null,
            'outstanding_pay_codes_minor' => $outstandingComplete ? $outstanding : null,
            'issuance_capacity_minor' => $issuanceComplete ? $issuance : null,
        ];
    }

    /** @param list<array<string, mixed>> $payCodes
     * @return array<string, int>
     */
    private function payCodeStates(array $payCodes): array
    {
        $states = [];

        foreach ($payCodes as $payCode) {
            $state = trim((string) ($payCode['state'] ?? 'unknown')) ?: 'unknown';
            $states[$state] = ($states[$state] ?? 0) + 1;
        }

        ksort($states);

        return $states;
    }

    /** @param list<array<string, mixed>> $accounts */
    private function personalDataPresent(array $accounts): bool
    {
        foreach ($accounts as $account) {
            $profile = $account['profile'] ?? null;

            if (is_array($profile) && ($profile['redacted'] ?? false) !== true) {
                return true;
            }
        }

        return false;
    }

    private function removeDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory.'/'.$entry;
            is_dir($path) ? $this->removeDirectory($path) : @unlink($path);
        }

        @rmdir($directory);
    }
}
