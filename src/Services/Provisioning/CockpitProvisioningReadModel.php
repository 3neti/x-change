<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Provisioning;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use LBHurtado\XChange\Enums\ProvisioningOperatorCapability;
use LBHurtado\XProvisioning\Enums\ProvisioningProfile;
use LBHurtado\XProvisioning\Models\ProvisioningRequest;
use LBHurtado\XProvisioning\Models\ProvisioningSeat;

final readonly class CockpitProvisioningReadModel
{
    public function __construct(private ProvisioningOperatorAuthority $authority) {}

    /** @return array<string, mixed> */
    public function build(Model $operator): array
    {
        if (! Schema::hasTable('x_provisioning_requests')) {
            return $this->empty($operator);
        }

        $seats = ProvisioningSeat::query()
            ->with('request:id,reference,status')
            ->orderByDesc('required')
            ->orderBy('label')
            ->get()
            ->map(fn (ProvisioningSeat $seat): array => [
                'reference' => $seat->reference,
                'key' => $seat->seat_key,
                'label' => $seat->label,
                'profile' => $seat->profile->value,
                'profile_label' => $this->profileLabel($seat->profile),
                'required' => $seat->required,
                'status' => $seat->status->value,
                'request_reference' => $seat->request?->reference,
            ])->all();

        $requestModels = ProvisioningRequest::query()
            ->with([
                'revisions' => fn ($query) => $query->latest('version'),
                'offer.acceptance',
                'events' => fn ($query) => $query->latest('occurred_at')->limit(12),
            ])
            ->latest()
            ->limit(100)
            ->get();
        $requests = $requestModels
            ->map(function (ProvisioningRequest $request) use ($requestModels): array {
                $revision = $request->revisions->firstWhere('version', $request->current_revision_number)
                    ?? $request->revisions->first();
                $offer = $request->offer;
                $replacementOptions = $offer === null ? [] : $requestModels
                    ->filter(fn (ProvisioningRequest $candidate): bool => $candidate->getKey() !== $request->getKey()
                        && $candidate->profile === $request->profile
                        && $candidate->status->value === 'activated'
                        && $candidate->offer?->acceptance?->candidate_type === $offer->acceptance?->candidate_type
                        && $candidate->offer?->acceptance?->candidate_reference === $offer->acceptance?->candidate_reference)
                    ->map(fn (ProvisioningRequest $candidate): array => [
                        'offer_reference' => $candidate->offer?->reference,
                        'request_reference' => $candidate->reference,
                        'purpose' => (string) data_get(
                            $candidate->revisions->firstWhere('version', $candidate->current_revision_number)?->snapshot,
                            'purpose',
                            '',
                        ),
                    ])->values()->all();

                return [
                    'reference' => $request->reference,
                    'profile' => $request->profile->value,
                    'profile_label' => $this->profileLabel($request->profile),
                    'status' => $request->status->value,
                    'commissioning' => $request->commissioning,
                    'purpose' => (string) data_get($revision?->snapshot, 'purpose', ''),
                    'required_evidence' => array_values((array) data_get($revision?->snapshot, 'required_evidence', [])),
                    'capabilities' => array_values((array) data_get($revision?->snapshot, 'capabilities', [])),
                    'activation_gate' => (string) data_get($revision?->snapshot, 'activation_gate', 'operator_authority'),
                    'recipient_designation' => $request->profile === ProvisioningProfile::CommercialRecipientDesignation
                        ? [
                            'counterparty_reference' => (string) data_get($revision?->snapshot, 'counterparty_reference', ''),
                            'commercial_role' => (string) data_get($revision?->snapshot, 'commercial_role', ''),
                            'agreement_reference' => (string) data_get($revision?->snapshot, 'agreement_reference', ''),
                            'settlement_designation_reference' => (string) data_get($revision?->snapshot, 'settlement_designation_reference', ''),
                            'supersedes_designation_reference' => (string) data_get($revision?->snapshot, 'supersedes_designation_reference', ''),
                            'settlement_disposition' => (string) data_get($revision?->snapshot, 'settlement_disposition', ''),
                            'settlement_account_binding' => (string) data_get($revision?->snapshot, 'settlement_account_binding', 'exact_account'),
                            'component_scope' => array_values((array) data_get($revision?->snapshot, 'component_scope', [])),
                        ]
                        : null,
                    'snapshot_hash' => $revision?->snapshot_hash,
                    'revision' => $revision?->version,
                    'submitted_at' => $revision?->submitted_at?->toIso8601String(),
                    'approved_at' => $revision?->approved_at?->toIso8601String(),
                    'offer' => $offer === null ? null : [
                        'reference' => $offer->reference,
                        'status' => $offer->status->value,
                        'expires_at' => $offer->expires_at?->toIso8601String(),
                        'accepted_at' => $offer->accepted_at?->toIso8601String(),
                        'activated_at' => $offer->activated_at?->toIso8601String(),
                        'candidate_bound' => $offer->acceptance !== null,
                        'activation_reference' => $offer->activation_reference,
                        'revoked_at' => $offer->revoked_at?->toIso8601String(),
                        'actions' => [
                            'activate' => route('x-change.cockpit.provisioning.offers.activations.store', $offer),
                            'revoke' => route('x-change.cockpit.provisioning.offers.revocations.store', $offer),
                            'supersede' => route('x-change.cockpit.provisioning.offers.supersessions.store', $offer),
                        ],
                        'replacement_options' => $replacementOptions,
                    ],
                    'events' => $request->events->map(fn ($event): array => [
                        'type' => $event->event_type,
                        'occurred_at' => $event->occurred_at?->toIso8601String(),
                    ])->values()->all(),
                    'actions' => [
                        'approve' => route('x-change.cockpit.provisioning.requests.approvals.store', $request),
                        'reject' => route('x-change.cockpit.provisioning.requests.rejections.store', $request),
                        'withdraw' => route('x-change.cockpit.provisioning.requests.withdrawals.store', $request),
                        'issue' => route('x-change.cockpit.provisioning.requests.offers.store', $request),
                    ],
                    'created_at' => $request->created_at?->toIso8601String(),
                ];
            })->all();

        return [
            'schema' => 'x-change.cockpit.provisioning.v1',
            'capabilities' => $this->capabilities($operator),
            'stats' => [
                'vacant_seats' => collect($seats)->where('status', 'vacant')->count(),
                'awaiting_approval' => collect($requests)->where('status', 'awaiting_approval')->count(),
                'outstanding_offers' => collect($requests)->whereIn('status', ['approved', 'offered', 'activation_pending'])->count(),
                'activated' => collect($requests)->where('status', 'activated')->count(),
            ],
            'profiles' => $this->profiles(),
            'seats' => $seats,
            'requests' => $requests,
        ];
    }

    /** @return array<string, bool> */
    private function capabilities(Model $operator): array
    {
        return collect(ProvisioningOperatorCapability::cases())
            ->mapWithKeys(fn (ProvisioningOperatorCapability $capability): array => [
                Str::after($capability->value, 'provisioning.') => $this->authority->allows($operator, $capability),
            ])->all();
    }

    /** @return list<array{value:string,label:string,description:string,capabilities:list<string>,activation_gate:string}> */
    private function profiles(): array
    {
        return collect((array) config('x-change.provisioning.operator_profiles', []))
            ->map(fn (array $profile, string $value): array => [
                'value' => $value,
                'label' => (string) ($profile['label'] ?? Str::headline($value)),
                'description' => (string) ($profile['description'] ?? ''),
                'capabilities' => array_values((array) ($profile['capabilities'] ?? [])),
                'activation_gate' => (string) ($profile['activation_gate'] ?? 'operator_authority'),
            ])->values()->all();
    }

    private function profileLabel(ProvisioningProfile $profile): string
    {
        return (string) config(
            "x-change.provisioning.operator_profiles.{$profile->value}.label",
            Str::headline($profile->value),
        );
    }

    /** @return array<string, mixed> */
    private function empty(Model $operator): array
    {
        return [
            'schema' => 'x-change.cockpit.provisioning.v1',
            'capabilities' => $this->capabilities($operator),
            'stats' => ['vacant_seats' => 0, 'awaiting_approval' => 0, 'outstanding_offers' => 0, 'activated' => 0],
            'profiles' => $this->profiles(),
            'seats' => [],
            'requests' => [],
        ];
    }
}
