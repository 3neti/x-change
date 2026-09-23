<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Leads;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Models\CampaignPaymentSource;
use LBHurtado\XChange\Models\LeadCampaign;
use LBHurtado\XChange\Models\PaymentAttempt;
use LBHurtado\XChange\Models\VoucherClaim;
use LBHurtado\XChange\Services\Cockpit\CampaignPolicyLifecycleReadModel;

final readonly class LeadCampaignLifecycleScenarioRunReadModel
{
    public function __construct(
        private CampaignPolicyLifecycleReadModel $policyLifecycles,
    ) {}

    /** @return array<string, mixed> */
    public function for(LeadCampaign $campaign, Model $owner): array
    {
        $template = $campaign->payCodeTemplate()->firstOrFail();
        $run = (array) data_get($campaign->settings, 'scenario_run', []);
        $voucher = $this->voucher($campaign, $owner);
        $claim = $voucher instanceof Voucher
            ? VoucherClaim::query()->where('voucher_id', $voucher->getKey())->latest('id')->first()
            : null;
        $attempt = $voucher instanceof Voucher
            ? PaymentAttempt::query()->with('events')->where('voucher_id', $voucher->getKey())->latest('id')->first()
            : null;
        $paymentSource = $attempt instanceof PaymentAttempt
            ? CampaignPaymentSource::query()->with('recognition')->where('payment_attempt_id', $attempt->getKey())->first()
            : null;
        $recognition = $paymentSource?->recognition;
        $lifecycle = collect($this->policyLifecycles->forOwner($owner))
            ->first(fn ($item): bool => data_get($item->campaign, 'reference') === $campaign->reference);
        $driverId = (string) data_get($run, 'envelope_driver_id');
        $driverVersion = (string) data_get($run, 'envelope_driver_version');
        $driverAvailable = $driverId !== '' && (
            is_file(config_path('envelope-drivers/'.$driverId.'.yaml'))
            || is_file(dirname(__DIR__, 3).'/config/envelope-drivers/'.$driverId.'.yaml')
        );
        $claimIntakeCompleted = $claim !== null
            && (
                in_array($claim->status, ['pending', 'succeeded'], true)
                || $attempt !== null
            );
        $attemptSettled = $attempt?->status?->value === 'settled';
        $collectionRecorded = $attempt?->voucher_collection_id !== null;

        $steps = [
            $this->step(1, 'Preconditions checked', 'passed', 'The authenticated operator and browser scenario gate were accepted.', $campaign->created_at),
            $this->step(2, 'Settlement template selected', 'passed', 'The campaign is pinned to an immutable Pay Code template version.', $template->created_at, [
                'template_reference' => $template->reference,
                'template_version' => $campaign->active_template_version_id,
            ]),
            $this->step(3, 'Envelope driver resolved', $driverAvailable ? 'passed' : 'failed', $driverAvailable
                ? 'The demonstration settlement-envelope YAML is available for the exact driver version.'
                : 'The required settlement-envelope YAML is unavailable.', $driverAvailable ? $campaign->created_at : null, [
                    'driver' => $driverId.'@'.$driverVersion,
                    'contract_authority' => 'demonstration_only',
                ]),
            $this->step(4, 'Endpoint campaign created', 'passed', 'The durable campaign record is the scenario-run anchor.', $campaign->created_at, [
                'campaign_reference' => $campaign->reference,
                'run_reference' => data_get($run, 'reference'),
            ]),
            $this->step(5, 'Campaign endpoint opened', $campaign->usage_count > 0 ? 'passed' : 'waiting_for_person', $campaign->usage_count > 0
                ? 'The endpoint successfully generated a fresh Pay Code.'
                : 'Open the public endpoint in the applicant browser to continue.', $campaign->last_started_at),
            $this->step(6, 'Settlement Pay Code issued', $voucher ? 'passed' : 'not_started', $voucher
                ? 'A campaign-attributed settlement Pay Code was persisted.'
                : 'No campaign-attributed Pay Code exists yet.', $voucher?->created_at, $voucher ? ['pay_code' => $voucher->code] : []),
            $this->step(7, 'Applicant intake and identity verification completed', $claimIntakeCompleted ? 'passed' : ($voucher ? 'waiting_for_person' : 'not_started'), $claimIntakeCompleted
                ? 'The claim completed through the configured intake flow. Private answers remain outside this run log.'
                : 'The applicant must complete the claim and configured verification steps.', $claim?->completed_at, $claim ? ['claim_number' => $claim->claim_number] : []),
            $this->step(8, 'Payment attempt created', $attempt ? 'passed' : ($claimIntakeCompleted ? 'waiting_for_person' : 'not_started'), $attempt
                ? 'A provider payment attempt was persisted for the settlement target.'
                : 'Continue from claim success to the payment page and generate payment instructions.', $attempt?->created_at, $attempt ? ['payment_attempt' => $attempt->reference] : []),
            $this->step(9, 'QR Ph generated', $attempt?->instructions_created_at ? 'passed' : ($attempt ? 'running' : 'not_started'), $attempt?->instructions_created_at
                ? 'Provider payment instructions include a generated QR artifact.'
                : 'QR Ph has not yet been generated.', $attempt?->instructions_created_at, $attempt ? ['mode' => data_get($attempt->instructions_ciphertext, 'qr_code.qr_mode', 'provider')] : []),
            $this->step(10, 'Provider payment observed', $attemptSettled ? 'passed' : ($attempt ? 'waiting_for_provider' : 'not_started'), $attemptSettled
                ? 'The payment attempt reached authoritative settled status.'
                : 'Waiting for provider evidence. No payment is inferred from QR generation.', $attempt?->settled_at, $attempt ? ['provider' => $attempt->provider_code, 'status' => $attempt->status?->value] : []),
            $this->step(11, 'Collection recorded', $collectionRecorded ? 'passed' : ($attemptSettled ? 'running' : 'not_started'), $collectionRecorded
                ? 'Exactly one voucher collection is linked to the settled payment attempt.'
                : 'No collection record is linked yet.', $attempt?->verified_at, $collectionRecorded ? ['collection_id' => $attempt->voucher_collection_id] : []),
            $this->step(12, 'Campaign lifecycle projected', $lifecycle ? 'passed' : ($recognition ? 'running' : 'not_started'), $lifecycle
                ? 'Coverage, envelope, completion Pay Code, and policy lifecycle facts are available in the owner-scoped projection.'
                : ($recognition
                    ? 'The settlement payment is recognized. Provisional coverage and completion issuance remain a separate controlled gate.'
                    : 'The settlement-payment recognition bridge has not produced a campaign lifecycle for this run.'), $lifecycle?->updatedAt ?? $recognition?->recognized_at, $lifecycle ? ['stage' => $lifecycle->stage] : ($recognition ? ['recognition_reference' => $recognition->reference] : [])),
            $this->step(13, 'Completion Pay Code claimed', data_get($lifecycle?->completion, 'claim_completed_at')
                ? 'passed'
                : (data_get($lifecycle?->completion, 'pay_code') ? 'waiting_for_person' : 'not_started'), data_get($lifecycle?->completion, 'claim_completed_at')
                ? 'The applicant completed the zero-value Pay Code and a redacted evidence manifest was projected into the settlement envelope.'
                : (data_get($lifecycle?->completion, 'pay_code')
                    ? 'Open the completion Pay Code and submit the remaining applicant requirements.'
                    : 'No completion Pay Code is available yet.'), data_get($lifecycle?->completion, 'claim_completed_at'), array_filter([
                        'pay_code' => data_get($lifecycle?->completion, 'pay_code'),
                        'projection_reference' => data_get($lifecycle?->completion, 'projection_reference'),
                    ])),
            $this->step(14, 'Policy completion governance', $this->policyGovernanceStatus($lifecycle?->stage), $this->policyGovernanceDescription($lifecycle?->stage), $lifecycle?->updatedAt, $lifecycle ? ['stage' => $lifecycle->stage] : []),
        ];

        $terminalFailure = collect($steps)->contains(fn (array $step): bool => $step['status'] === 'failed');
        $complete = $lifecycle !== null && in_array($lifecycle->stage, ['policy_succeeded', 'policy_failed', 'policy_indeterminate'], true);

        return [
            'schema' => 'x-change.cockpit.lead-campaign-lifecycle-run.v1',
            'reference' => (string) data_get($run, 'reference', $campaign->reference),
            'scenario' => (string) data_get($run, 'scenario', 'aui_on_demand_insurance_payment'),
            'title' => $campaign->title,
            'mode' => (string) data_get($run, 'mode', 'browser_manual_payment'),
            'status' => $terminalFailure ? 'failed' : ($complete ? 'completed' : 'running'),
            'started_at' => data_get($run, 'started_at', $campaign->created_at?->toIso8601String()),
            'updated_at' => $this->latestTimestamp($steps),
            'declarations' => [
                'payment_evidence' => $attemptSettled ? 'provider_observed' : 'not_observed',
                'financial_mode' => 'real_provider_when_paid',
                'insurer_contract' => 'not_configured',
                'driver_authority' => 'demonstration_only',
            ],
            'steps' => $steps,
            'artifacts' => $this->artifacts($campaign, $template, $voucher, $claim, $attempt, $recognition, $lifecycle),
        ];
    }

    private function voucher(LeadCampaign $campaign, Model $owner): ?Voucher
    {
        return Voucher::query()
            ->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', (string) $owner->getKey())
            ->latest('id')
            ->limit(100)
            ->get()
            ->first(fn (Voucher $voucher): bool => data_get(
                $voucher->metadata,
                'instructions.metadata.custom.lead_campaign.campaign_reference',
            ) === $campaign->reference);
    }

    /** @param array<string, mixed> $facts */
    private function step(int $sequence, string $label, string $status, string $description, mixed $occurredAt = null, array $facts = []): array
    {
        return [
            'sequence' => $sequence,
            'label' => $label,
            'status' => $status,
            'description' => $description,
            'occurred_at' => $occurredAt instanceof CarbonInterface ? $occurredAt->toIso8601String() : $occurredAt,
            'facts' => $facts,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function artifacts(LeadCampaign $campaign, mixed $template, ?Voucher $voucher, ?VoucherClaim $claim, ?PaymentAttempt $attempt, mixed $recognition, mixed $lifecycle): array
    {
        $artifacts = [
            ['group' => 'Campaign', 'label' => 'Scenario run', 'reference' => data_get($campaign->settings, 'scenario_run.reference'), 'href' => null, 'evidence' => 'application_persisted'],
            ['group' => 'Campaign', 'label' => 'Public endpoint', 'reference' => '/x/o/'.$campaign->merchant_slug.'/'.$campaign->endpoint_slug, 'href' => route('x-change.leads.start', [$campaign->merchant_slug, $campaign->endpoint_slug]), 'evidence' => 'application_persisted'],
            ['group' => 'Campaign', 'label' => 'Template', 'reference' => $template->reference, 'href' => null, 'evidence' => 'configuration'],
            ['group' => 'Settlement', 'label' => 'Envelope driver', 'reference' => data_get($campaign->settings, 'scenario_run.envelope_driver_id').'@'.data_get($campaign->settings, 'scenario_run.envelope_driver_version'), 'href' => null, 'evidence' => 'configuration'],
        ];

        if ($voucher instanceof Voucher) {
            $artifacts[] = ['group' => 'Pay Code', 'label' => 'Settlement Pay Code', 'reference' => $voucher->code, 'href' => route('x-change.cockpit.pay-codes.show', $voucher->code), 'evidence' => 'application_persisted'];
            $artifacts[] = ['group' => 'Pay Code', 'label' => 'Applicant claim', 'reference' => '/x/claim/'.$voucher->code, 'href' => route('x-change.claim.show', $voucher->code), 'evidence' => $claim !== null && (in_array($claim->status, ['pending', 'succeeded'], true) || $attempt !== null) ? 'person_confirmed' : 'application_persisted'];
            $artifacts[] = ['group' => 'Payment', 'label' => 'Payment page', 'reference' => '/x/pay/'.$voucher->code, 'href' => route('x-change.pay.show', array_filter(['code' => $voucher->code, 'attempt' => $attempt?->reference])), 'evidence' => 'application_persisted'];
        }

        if ($attempt instanceof PaymentAttempt) {
            $artifacts[] = ['group' => 'Payment', 'label' => 'Payment attempt', 'reference' => $attempt->reference, 'href' => null, 'evidence' => $attempt->status?->value === 'settled' ? 'provider_observed' : 'application_persisted'];
        }

        if ($recognition !== null) {
            $artifacts[] = ['group' => 'Campaign', 'label' => 'Payment recognition', 'reference' => $recognition->reference, 'href' => null, 'evidence' => 'provider_observed'];
        }

        if ($lifecycle !== null) {
            $artifacts[] = ['group' => 'Policy', 'label' => 'Policy lifecycle', 'reference' => data_get($lifecycle->completion, 'projection_reference', $campaign->reference), 'href' => route('x-change.cockpit.campaigns.policy-lifecycle.index'), 'evidence' => 'derived_projection'];
            $artifacts[] = ['group' => 'Coverage', 'label' => 'Provisional coverage', 'reference' => data_get($lifecycle->coverage, 'reference'), 'href' => route('x-change.cockpit.campaigns.policy-lifecycle.index'), 'evidence' => 'application_persisted'];
            $completionCode = data_get($lifecycle->completion, 'pay_code');
            if (is_string($completionCode) && $completionCode !== '') {
                $artifacts[] = ['group' => 'Pay Code', 'label' => 'Completion Pay Code', 'reference' => $completionCode, 'href' => route('x-change.cockpit.pay-codes.show', $completionCode), 'evidence' => 'application_persisted'];
            }
        }

        return $artifacts;
    }

    /** @param list<array<string, mixed>> $steps */
    private function latestTimestamp(array $steps): ?string
    {
        return collect($steps)->pluck('occurred_at')->filter()->sort()->last();
    }

    private function policyGovernanceStatus(?string $stage): string
    {
        return match ($stage) {
            'claim_evidence_ready' => 'waiting_for_person',
            'policy_awaiting_approval', 'policy_authorized' => 'running',
            'policy_succeeded', 'policy_failed', 'policy_indeterminate' => 'passed',
            default => 'not_started',
        };
    }

    private function policyGovernanceDescription(?string $stage): string
    {
        return match ($stage) {
            'claim_evidence_ready' => 'Claim evidence is ready. Maker/checker authorization remains a separate controlled gate.',
            'policy_awaiting_approval' => 'A policy completion request is awaiting checker approval.',
            'policy_authorized' => 'The policy completion request is authorized; provider transport remains separately controlled.',
            'policy_succeeded' => 'The separately authorized policy completion recorded a successful outcome.',
            'policy_failed' => 'The separately authorized policy completion recorded a failed outcome.',
            'policy_indeterminate' => 'The separately authorized policy completion requires reconciliation.',
            default => 'Policy completion governance has not started.',
        };
    }
}
