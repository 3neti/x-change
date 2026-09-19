<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Continuity;

use LBHurtado\XChange\Exceptions\InstanceKeepsakeException;
use LBHurtado\XChange\Services\Keepsake\CanonicalKeepsakeJson;
use LBHurtado\XChange\Services\Keepsake\InspectInstanceKeepsakeArchive;

final readonly class PlanInstanceContinuityRecovery
{
    public function __construct(
        private InspectInstanceKeepsakeArchive $inspector,
        private CanonicalKeepsakeJson $json,
    ) {}

    /** @return array<string, mixed> */
    public function handle(
        string $archivePath,
        string $keyPath,
        string $expectedArchiveHash,
        string $destinationInstance,
    ): array {
        $destinationInstance = trim($destinationInstance);

        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9:._-]{2,127}$/', $destinationInstance) !== 1) {
            throw new InstanceKeepsakeException('destination_required', 'Provide a stable destination instance identifier.');
        }

        $inspection = $this->inspector->handle($archivePath, $keyPath, $expectedArchiveHash);
        $inventory = $inspection['inventory'];
        $financial = $inspection['financial_observation'];
        $blockers = [
            'source_instance_identity_not_embedded',
            'provider_checkpoint_not_embedded',
            'campaigns_not_included',
            'live_pay_code_restore_not_supported',
            'financial_apply_not_supported',
        ];

        if (($inspection['complete'] ?? false) !== true) {
            $blockers[] = 'keepsake_has_omissions';
        }

        if (($inspection['privacy']['personal_data_present'] ?? false) !== true
            && (int) ($inventory['accounts'] ?? 0) > 0) {
            $blockers[] = 'account_identity_data_redacted';
        }

        sort($blockers);
        $proposal = [
            'destination_instance' => $destinationInstance,
            'archive_sha256' => $inspection['archive_sha256'],
            'manifest_sha256' => $inspection['manifest_sha256'],
            'source_plan_hash' => $inspection['plan_hash'],
            'inventory' => $inventory,
            'financial_observation' => $financial,
            'pay_code_states' => $inspection['pay_code_states'],
            'proposed_actions' => [
                'accounts' => 'identity_match_and_reverification',
                'account_invitations' => 'review_inert_blueprint',
                'pay_code_templates' => 'review_and_recreate_from_inert_blueprint',
                'historical_pay_codes' => 'retain_as_evidence_only',
                'claim_evidence' => 'retain_for_authorized_review',
                'client_funds' => 'reconcile_then_prepare_separate_authorized_credit_plan',
                'active_pay_codes' => 'operator_disposition_required',
            ],
            'blockers' => $blockers,
        ];

        return [
            'schema' => 'x-change.instance-continuity-plan.v1',
            'status' => 'review_required',
            'continuity_plan_hash' => $this->json->hash($proposal),
            ...$proposal,
            'requires_maker_checker' => true,
            'apply_supported' => false,
            'read_only' => true,
            'writes_database' => false,
            'writes_storage' => false,
            'provider_calls' => false,
            'moves_money' => false,
            'restores_live_pay_codes' => false,
            'safe_to_reset' => false,
            'message' => 'This deterministic plan is evidence for review. It is not restoration authority.',
        ];
    }
}
