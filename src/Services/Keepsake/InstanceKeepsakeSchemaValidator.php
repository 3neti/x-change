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
            'x-change.instance-keepsake.endpoint-campaigns.v1' => $this->validateEndpointCampaigns($decoded),
            'x-change.instance-keepsake.endpoint-campaign-blueprints.v1' => $this->validateEndpointCampaignBlueprints($decoded),
            'x-change.instance-keepsake.continuity-checkpoint.v1' => $this->validateContinuityCheckpoint($decoded),
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
    private function validateEndpointCampaigns(array $document): void
    {
        $this->assertExactKeys($document, ['schema', 'campaigns']);
        $this->assertList($document['campaigns'] ?? null, [
            'reference', 'source_reference', 'account_reference', 'template_reference',
            'active_template_version_id', 'merchant_display_name', 'merchant_slug',
            'endpoint_slug', 'title', 'description', 'status', 'usage_count',
            'last_started_at', 'starts_limit', 'expires_at', 'created_at', 'updated_at',
            'settings_included', 'historical_only', 'restorable',
        ]);

        foreach ($document['campaigns'] as $campaign) {
            if (($campaign['settings_included'] ?? null) !== false
                || ($campaign['historical_only'] ?? null) !== true
                || ($campaign['restorable'] ?? null) !== false) {
                $this->invalid();
            }
        }
    }

    /** @param array<string, mixed> $document */
    private function validateEndpointCampaignBlueprints(array $document): void
    {
        $this->assertExactKeys($document, ['schema', 'inert', 'importer_included', 'campaigns']);
        $this->assertList($document['campaigns'] ?? null, [
            'reference', 'account_reference', 'template_reference', 'merchant_display_name',
            'merchant_slug', 'endpoint_slug', 'title', 'description', 'starts_limit',
            'expires_at', 'desired_state', 'settings_included',
            'merchant_certification_included', 'activation_authority_included', 'requires_review',
        ]);

        if (($document['inert'] ?? null) !== true || ($document['importer_included'] ?? null) !== false) {
            $this->invalid();
        }

        foreach ($document['campaigns'] as $campaign) {
            if (($campaign['desired_state'] ?? null) !== 'disabled'
                || ($campaign['settings_included'] ?? null) !== false
                || ($campaign['merchant_certification_included'] ?? null) !== false
                || ($campaign['activation_authority_included'] ?? null) !== false
                || ($campaign['requires_review'] ?? null) !== true) {
                $this->invalid();
            }
        }
    }

    /** @param array<string, mixed> $document */
    private function validateContinuityCheckpoint(array $document): void
    {
        $this->assertExactKeys($document, [
            'schema', 'source_instance', 'observed_at', 'provider_calls',
            'ownership_authority', 'provider_balance_snapshots',
        ]);
        $source = $document['source_instance'] ?? null;

        if (! is_array($source)) {
            $this->invalid();
        }

        $this->assertExactKeys($source, ['id', 'name', 'url', 'deployment_profile', 'runtime_tier']);
        $this->assertList($document['provider_balance_snapshots'] ?? null, [
            'provider_code', 'balance_key', 'scope_key', 'balance_minor',
            'available_balance_minor', 'currency', 'account_reference_masked',
            'provider_as_of', 'fetched_at', 'refresh_status', 'updated_at',
            'is_stale', 'maximum_age_seconds', 'authority', 'restoration_authority',
        ]);

        if (($document['provider_calls'] ?? null) !== false || ($document['ownership_authority'] ?? null) !== false) {
            $this->invalid();
        }

        foreach ($document['provider_balance_snapshots'] as $snapshot) {
            if (($snapshot['authority'] ?? null) !== 'observational_snapshot'
                || ($snapshot['restoration_authority'] ?? null) !== false
                || ! is_bool($snapshot['is_stale'] ?? null)
                || ! is_int($snapshot['maximum_age_seconds'] ?? null)) {
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
