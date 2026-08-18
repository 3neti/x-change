<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Keepsake;

use LBHurtado\XChange\Exceptions\InstanceKeepsakeException;

final class InstanceKeepsakeSchemaValidator
{
    public function validate(string $path, string $contents): void
    {
        try {
            $decoded = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new InstanceKeepsakeException('schema_invalid', 'A keepsake JSON document is invalid.');
        }

        if (! is_array($decoded) || ! is_string($decoded['schema'] ?? null)) {
            throw new InstanceKeepsakeException('schema_invalid', 'A keepsake JSON document is invalid.');
        }

        if (str_starts_with($path, 'blueprint/')) {
            $this->assertInertBlueprint($decoded);
        }

        match ($decoded['schema']) {
            'x-change.instance-keepsake.manifest.v1' => $this->validateManifest($decoded),
            'x-change.instance-keepsake.accounts.v1' => $this->validateAccounts($decoded),
            'x-change.instance-keepsake.pay-codes.v1' => $this->validatePayCodes($decoded),
            'x-change.instance-keepsake.claim-evidence.v1' => $this->validateClaimEvidence($decoded),
            'x-change.instance-keepsake.location.v1' => $this->validateLocation($decoded),
            'x-change.instance-keepsake.account-invitations.v1' => $this->validateAccountInvitations($decoded),
            'x-change.instance-keepsake.pay-code-templates.v1' => $this->validatePayCodeTemplates($decoded),
            default => throw new InstanceKeepsakeException('schema_invalid', 'A keepsake JSON document uses an unknown schema.'),
        };

    }

    /** @param array<string, mixed> $document */
    private function validateManifest(array $document): void
    {
        $this->assertExactKeys($document, [
            'schema', 'plan_hash', 'observed_at', 'created_at', 'package_version', 'complete',
            'omission_count', 'encrypted', 'restoration_authority', 'entries',
        ]);
        $this->assertList($document['entries'] ?? null, [
            'path', 'mime_type', 'size', 'sha256',
        ]);

        if (($document['encrypted'] ?? null) !== true || ($document['restoration_authority'] ?? null) !== false) {
            $this->invalid();
        }
    }

    /** @param array<string, mixed> $document */
    private function validateAccounts(array $document): void
    {
        $this->assertExactKeys($document, ['schema', 'accounts']);
        $this->assertList($document['accounts'] ?? null, [
            'account_reference', 'profile', 'currency', 'client_funds_minor',
            'outstanding_pay_codes_minor', 'issuance_capacity_minor', 'observed_at',
            'authority', 'restorable', 'reconciliation_required',
        ]);

        foreach ($document['accounts'] as $account) {
            $this->validateProfile($account['profile'] ?? null);

            if (($account['authority'] ?? null) !== 'observational_snapshot'
                || ($account['restorable'] ?? null) !== false
                || ($account['reconciliation_required'] ?? null) !== true) {
                $this->invalid();
            }
        }
    }

    /** @param array<string, mixed> $document */
    private function validatePayCodes(array $document): void
    {
        $this->assertExactKeys($document, ['schema', 'issued_codes_are_historical_only', 'pay_codes']);
        $this->assertList($document['pay_codes'] ?? null, [
            'reference', 'account_reference', 'code_fingerprint', 'state', 'amount_minor', 'currency',
            'created_at', 'expires_at', 'redeemed_at', 'historical_only', 'restorable',
        ]);

        if (($document['issued_codes_are_historical_only'] ?? null) !== true) {
            $this->invalid();
        }

        foreach ($document['pay_codes'] as $payCode) {
            if (($payCode['historical_only'] ?? null) !== true || ($payCode['restorable'] ?? null) !== false) {
                $this->invalid();
            }
        }
    }

    /** @param array<string, mixed> $document */
    private function validateClaimEvidence(array $document): void
    {
        $this->assertExactKeys($document, ['schema', 'blueprint_eligible', 'records', 'omissions']);
        $this->assertList($document['records'] ?? null, [
            'requirement', 'source', 'archive_path', 'mime_type', 'size', 'sha256', 'captured_at',
        ]);
        $this->assertList($document['omissions'] ?? null, ['reason', 'reference']);

        if (($document['blueprint_eligible'] ?? null) !== false) {
            $this->invalid();
        }
    }

    /** @param array<string, mixed> $document */
    private function validateLocation(array $document): void
    {
        $this->assertExactKeys($document, ['schema', 'sensitive', 'location']);

        if (($document['sensitive'] ?? null) !== true || ! is_array($document['location'] ?? null)) {
            $this->invalid();
        }
    }

    /** @param array<string, mixed> $document */
    private function validateAccountInvitations(array $document): void
    {
        $this->assertExactKeys($document, ['schema', 'inert', 'importer_included', 'invitations']);
        $this->assertList($document['invitations'] ?? null, [
            'reference', 'profile', 'desired_state', 'enabled', 'requires_reverification',
            'credentials_included', 'authority_included', 'financial_state_included',
        ]);

        if (($document['inert'] ?? null) !== true || ($document['importer_included'] ?? null) !== false) {
            $this->invalid();
        }

        foreach ($document['invitations'] as $invitation) {
            $this->validateProfile($invitation['profile'] ?? null);

            if (($invitation['desired_state'] ?? null) !== 'pending'
                || ($invitation['enabled'] ?? null) !== false
                || ($invitation['requires_reverification'] ?? null) !== true
                || ($invitation['credentials_included'] ?? null) !== false
                || ($invitation['authority_included'] ?? null) !== false
                || ($invitation['financial_state_included'] ?? null) !== false) {
                $this->invalid();
            }
        }
    }

    /** @param array<string, mixed> $document */
    private function validatePayCodeTemplates(array $document): void
    {
        $this->assertExactKeys($document, ['schema', 'inert', 'original_codes_included', 'templates']);
        $this->assertList($document['templates'] ?? null, [
            'reference', 'account_reference', 'name', 'description', 'base_template_key',
            'include_amount', 'include_purpose', 'instructions_included', 'requires_review',
        ]);

        if (($document['inert'] ?? null) !== true || ($document['original_codes_included'] ?? null) !== false) {
            $this->invalid();
        }

        foreach ($document['templates'] as $template) {
            if (($template['instructions_included'] ?? null) !== false || ($template['requires_review'] ?? null) !== true) {
                $this->invalid();
            }
        }
    }

    /** @param array<string, mixed> $document */
    private function assertInertBlueprint(array $document): void
    {
        $encoded = mb_strtolower(json_encode($document, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        foreach (['password', 'remember_token', 'private_key', 'oauth', 'api_secret', 'provider_secret', 'client_funds_minor', 'issuance_capacity_minor', 'treasury_position', 'journal_posting', 'claim_evidence_id'] as $forbidden) {
            if (str_contains($encoded, $forbidden)) {
                throw new InstanceKeepsakeException('blueprint_forbidden_field', 'The inert blueprint contains a forbidden field.');
            }
        }
    }

    private function validateProfile(mixed $profile): void
    {
        if (! is_array($profile)) {
            $this->invalid();
        }

        if (array_key_exists('redacted', $profile)) {
            $this->assertExactKeys($profile, ['redacted']);

            if ($profile['redacted'] !== true) {
                $this->invalid();
            }

            return;
        }

        $this->assertExactKeys($profile, ['name', 'email', 'mobile']);

        foreach ($profile as $value) {
            if ($value !== null && ! is_string($value)) {
                $this->invalid();
            }
        }
    }

    /** @param array<string, mixed> $document
     * @param  list<string>  $allowed
     */
    private function assertExactKeys(array $document, array $allowed): void
    {
        $keys = array_keys($document);
        sort($keys);
        sort($allowed);

        if ($keys !== $allowed) {
            $this->invalid();
        }
    }

    /** @param list<string> $allowedItemKeys */
    private function assertList(mixed $items, array $allowedItemKeys): void
    {
        if (! is_array($items) || ! array_is_list($items)) {
            $this->invalid();
        }

        foreach ($items as $item) {
            if (! is_array($item)) {
                $this->invalid();
            }

            $this->assertExactKeys($item, $allowedItemKeys);
        }
    }

    private function invalid(): never
    {
        throw new InstanceKeepsakeException('schema_invalid', 'A keepsake JSON document does not satisfy its packaged schema.');
    }
}
